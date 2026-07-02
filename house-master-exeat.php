<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if(!house_master_is_teacher()){
    header("location:".house_master_landing_page());
    exit();
}

$_TeacherId = $_SESSION['USERID'];
$_TeacherIdEsc = mysqli_real_escape_string($con, $_TeacherId);
$_HasHouseAssignment = house_master_has_assignment($con, $_SESSION['USERID']);
$_HasSeniorAssignment = house_master_has_senior_assignment($con, $_SESSION['USERID']);
$_CanManageExeat = house_master_can_manage_exeat($con, $_SESSION['USERID']);
$_ExeatScopeSql = house_master_exeat_scope_sql($con, $_SESSION['USERID'], 'er.houseid');
$_StudentScopeSql = house_master_exeat_scope_sql($con, $_SESSION['USERID'], 'sh.houseid');
$_ExeatScopeLabel = house_master_exeat_scope_label($con, $_SESSION['USERID']);
$_SeniorAssignment = $_HasSeniorAssignment ? get_senior_house_assignment($con, $_SESSION['USERID']) : null;
$_StudentGrantOptions = array();
$_ExpectedReturnSql = house_master_exeat_expected_return_sql('er');
$_OverdueSql = house_master_exeat_overdue_sql('er');

if(!function_exists('house_exeat_normalize_type')){
function house_exeat_normalize_type($value){
    $value = strtolower(trim((string)$value));
    return ($value === "internal") ? "internal" : (($value === "external") ? "external" : "");
}
}

if(!function_exists('house_exeat_notify_approval')){
function house_exeat_notify_approval($exeatMeta){
    $messages = array();
    $_ExeatType = house_exeat_normalize_type(isset($exeatMeta['exeattype']) ? $exeatMeta['exeattype'] : '');
    if($_ExeatType !== 'external'){
        $messages[] = "<div style='color:#1d4ed8;text-align:center;background-color:white'>Internal exeat approved. Parent SMS was not sent by policy.</div>";
        return $messages;
    }

    $_ParentPhone = trim((string)($exeatMeta['nextofkin_contact'] ?? ''));
    if($_ParentPhone === ""){
        $messages[] = "<div style='color:#b45309;text-align:center;background-color:white'>Exeat approved, but parent phone number is missing.</div>";
        return $messages;
    }

    $_StudentName = trim(
        (string)($exeatMeta['firstname'] ?? '')." ".
        (string)($exeatMeta['othernames'] ?? '')." ".
        (string)($exeatMeta['surname'] ?? '')
    );
    if($_StudentName === ""){
        $_StudentName = (string)($exeatMeta['userid'] ?? 'Student');
    }
    $_Departure = trim((string)($exeatMeta['dateout'] ?? ''))." ".trim((string)($exeatMeta['timeout'] ?? ''));
    $_Return = trim((string)($exeatMeta['datereturn'] ?? ''))." ".trim((string)($exeatMeta['timereturn'] ?? ''));
    $_SMSMessage = "Exeat approved for ".$_StudentName.". House: ".trim((string)($exeatMeta['housename'] ?? '')).". Out: ".trim($_Departure).". Return: ".trim($_Return).".";
    $_SMSCode = "";
    $_SMSSent = send_bulk_sms_message($_ParentPhone, $_SMSMessage, $_SMSCode);
    if($_SMSSent){
        $messages[] = "<div style='color:green;text-align:center;background-color:white'>Parent SMS sent successfully.</div>";
    }else{
        $messages[] = "<div style='color:#b45309;text-align:center;background-color:white'>Exeat approved, but parent SMS failed (code: ".htmlspecialchars((string)$_SMSCode).").</div>";
    }
    return $messages;
}
}

if(!function_exists('house_exeat_esc')){
function house_exeat_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('house_exeat_person_name')){
function house_exeat_person_name($row){
    $name = trim((string)($row['firstname'] ?? '')." ".(string)($row['othernames'] ?? '')." ".(string)($row['surname'] ?? ''));
    if($name === ""){
        $name = trim((string)($row['userid'] ?? ''));
    }
    return $name;
}
}

if(!function_exists('house_exeat_format_datetime')){
function house_exeat_format_datetime($datePart, $timePart = ""){
    $value = trim((string)$datePart." ".(string)$timePart);
    if($value === ""){
        return "-";
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y, H:i", $timestamp) : trim($value);
}
}

if(!function_exists('house_exeat_requested_datetime')){
function house_exeat_requested_datetime($value){
    $value = trim((string)$value);
    if($value === ""){
        return "-";
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y, H:i", $timestamp) : $value;
}
}

if(!function_exists('house_exeat_status_label')){
function house_exeat_status_label($row){
    $status = strtolower(trim((string)($row['status'] ?? '')));
    $hasReturned = trim((string)($row['actualreturndatetime'] ?? '')) !== "";
    $isOverdue = (int)($row['is_overdue'] ?? 0) === 1;
    if($status === 'pending'){
        return 'Pending';
    }
    if($status === 'approved' && $hasReturned){
        return 'Returned';
    }
    if($status === 'approved' && $isOverdue){
        return 'Overdue Return';
    }
    if($status === 'approved'){
        return 'Approved / Out';
    }
    if($status === 'rejected'){
        return 'Rejected';
    }
    return ucfirst($status);
}
}

if(!function_exists('house_exeat_status_class')){
function house_exeat_status_class($row){
    $status = strtolower(trim((string)($row['status'] ?? '')));
    $hasReturned = trim((string)($row['actualreturndatetime'] ?? '')) !== "";
    $isOverdue = (int)($row['is_overdue'] ?? 0) === 1;
    if($status === 'pending'){
        return 'he-badge he-badge--warning';
    }
    if($status === 'approved' && $hasReturned){
        return 'he-badge he-badge--success';
    }
    if($status === 'approved' && $isOverdue){
        return 'he-badge he-badge--danger';
    }
    if($status === 'approved'){
        return 'he-badge he-badge--info';
    }
    if($status === 'rejected'){
        return 'he-badge he-badge--neutral';
    }
    return 'he-badge he-badge--neutral';
}
}

if(!function_exists('house_exeat_type_class')){
function house_exeat_type_class($type){
    $type = strtolower(trim((string)$type));
    return $type === 'internal' ? 'he-badge he-badge--soft' : 'he-badge he-badge--brand';
}
}

if(isset($_POST['grant_exeat'])){
    @$_StudentId = trim((string)($_POST['studentid'] ?? ''));
    @$_ExeatType = house_exeat_normalize_type($_POST['exeattype'] ?? '');
    @$_Reason = trim((string)($_POST['reason'] ?? ''));
    @$_DateOut = trim((string)($_POST['dateout'] ?? ''));
    @$_TimeOut = trim((string)($_POST['timeout'] ?? ''));
    @$_DateReturn = trim((string)($_POST['datereturn'] ?? ''));
    @$_TimeReturn = trim((string)($_POST['timereturn'] ?? ''));
    @$_DecisionNote = trim((string)($_POST['decisionnote'] ?? ''));

    if(!$_CanManageExeat){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>You are not assigned to any exeat-managing role yet. Contact administration.</div>";
    }elseif($_StudentId === "" || $_ExeatType === "" || $_Reason === "" || $_DateOut === "" || $_TimeOut === "" || $_DateReturn === "" || $_TimeReturn === ""){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Student, exeat type, reason, departure date/time and return date/time are required.</div>";
    }else{
        $_OutDateTime = strtotime($_DateOut." ".$_TimeOut);
        $_ReturnDateTime = strtotime($_DateReturn." ".$_TimeReturn);
        if($_OutDateTime === false || $_ReturnDateTime === false){
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Invalid date/time provided.</div>";
        }elseif($_ReturnDateTime <= $_OutDateTime){
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Return date/time must be later than departure date/time.</div>";
        }else{
            $_StudentIdEsc = mysqli_real_escape_string($con, $_StudentId);
            $_SQLStudent = mysqli_query($con, "SELECT
                su.userid,su.firstname,su.surname,su.othernames,su.nextofkin_contact,
                h.houseid,h.housename
                FROM tblstudenthouse sh
                INNER JOIN tblsystemuser su ON su.userid=sh.userid
                INNER JOIN tblhouse h ON h.houseid=sh.houseid
                WHERE sh.userid='$_StudentIdEsc'
                  AND sh.status='active'
                  AND su.systemtype='Student'
                  AND su.status='active'
                  AND ".$_StudentScopeSql."
                LIMIT 1");
            if(!$_SQLStudent || mysqli_num_rows($_SQLStudent) === 0){
                $_ScopeMessage = $_HasSeniorAssignment
                    ? "You can only grant exeat to students who are assigned to an active house."
                    : "You can only grant exeat to students assigned to your active house.";
                $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>".$_ScopeMessage."</div>";
            }else{
                $_StudentMeta = mysqli_fetch_array($_SQLStudent, MYSQLI_ASSOC);
                $_PendingRes = mysqli_query($con, "SELECT exeatid FROM tblexeatrequest WHERE userid='$_StudentIdEsc' AND status='pending' LIMIT 1");
                if($_PendingRes && mysqli_num_rows($_PendingRes) > 0){
                    $_SESSION['Message'] = "<div style='color:#b45309;text-align:center;background-color:white'>This student already has a pending exeat request. Please review it in the queue instead of creating a direct grant.</div>";
                }else{
                    include("code.php");
                    $_ExeatId = $code;
                    $_HouseIdEsc = mysqli_real_escape_string($con, (string)$_StudentMeta['houseid']);
                    $_ExeatTypeEsc = mysqli_real_escape_string($con, $_ExeatType);
                    $_ReasonEsc = mysqli_real_escape_string($con, $_Reason);
                    $_DateOutEsc = mysqli_real_escape_string($con, $_DateOut);
                    $_TimeOutEsc = mysqli_real_escape_string($con, $_TimeOut);
                    $_DateReturnEsc = mysqli_real_escape_string($con, $_DateReturn);
                    $_TimeReturnEsc = mysqli_real_escape_string($con, $_TimeReturn);
                    if($_DecisionNote === ""){
                        $_DecisionNote = "Granted directly by house master/mistress.";
                    }
                    $_DecisionNoteEsc = mysqli_real_escape_string($con, $_DecisionNote);

                    $_SQLGrant = mysqli_query($con, "INSERT INTO tblexeatrequest(
                        exeatid,userid,houseid,exeattype,reason,dateout,timeout,datereturn,timereturn,requestedatetime,
                        status,decisionnote,decisionby,decisiondatetime,recordedby
                    ) VALUES(
                        '$_ExeatId','$_StudentIdEsc','$_HouseIdEsc','$_ExeatTypeEsc','$_ReasonEsc','$_DateOutEsc','$_TimeOutEsc','$_DateReturnEsc','$_TimeReturnEsc',NOW(),
                        'approved','$_DecisionNoteEsc','$_TeacherIdEsc',NOW(),'$_TeacherIdEsc'
                    )");
                    if($_SQLGrant){
                        $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Exeat granted successfully.</div>";
                        $_StudentMeta['exeattype'] = $_ExeatType;
                        $_StudentMeta['dateout'] = $_DateOut;
                        $_StudentMeta['timeout'] = $_TimeOut;
                        $_StudentMeta['datereturn'] = $_DateReturn;
                        $_StudentMeta['timereturn'] = $_TimeReturn;
                        foreach(house_exeat_notify_approval($_StudentMeta) as $_Notice){
                            $_SESSION['Message'] .= $_Notice;
                        }
                    }else{
                        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to grant exeat: ".mysqli_error($con)."</div>";
                    }
                }
            }
        }
    }
}

if(isset($_POST['decide_exeat'])){
    @$_ExeatId = $_POST['exeatid'];
    @$_Decision = $_POST['decision'];
    @$_Note = trim($_POST['decisionnote']);
    $_ExeatIdEsc = mysqli_real_escape_string($con, $_ExeatId);
    $_DecisionEsc = mysqli_real_escape_string($con, $_Decision);
    $_NoteEsc = mysqli_real_escape_string($con, $_Note);

    $_Allowed = false;
    $_CHK = mysqli_query($con, "SELECT er.userid
        FROM tblexeatrequest er
        WHERE er.exeatid='$_ExeatIdEsc'
          AND er.status='pending'
          AND ".$_ExeatScopeSql."
        LIMIT 1");
    if($_CHK && mysqli_num_rows($_CHK) > 0){
        $_Allowed = true;
    }

    if(!$_Allowed){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>You are not authorized to decide this request.</div>";
    }elseif($_Decision !== 'approved' && $_Decision !== 'rejected'){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Invalid decision.</div>";
    }else{
        $_ExeatMeta = null;
        $_SQL_META = mysqli_query($con, "SELECT er.*,h.housename,su.firstname,su.surname,su.othernames,su.nextofkin_contact
            FROM tblexeatrequest er
            INNER JOIN tblhouse h ON h.houseid=er.houseid
            INNER JOIN tblsystemuser su ON su.userid=er.userid
            WHERE er.exeatid='$_ExeatIdEsc'
            LIMIT 1");
        if($_SQL_META && $row_meta=mysqli_fetch_array($_SQL_META,MYSQLI_ASSOC)){
            $_ExeatMeta = $row_meta;
        }

        $_SQL = mysqli_query($con, "UPDATE tblexeatrequest
            SET status='$_DecisionEsc', decisionnote='$_NoteEsc', decisionby='$_TeacherIdEsc', decisiondatetime=NOW()
            WHERE exeatid='$_ExeatIdEsc' AND status='pending'");
        if($_SQL){
            $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Request updated successfully.</div>";

            if($_Decision === 'approved' && $_ExeatMeta){
                foreach(house_exeat_notify_approval($_ExeatMeta) as $_Notice){
                    $_SESSION['Message'] .= $_Notice;
                }
            }
        }else{
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to update request: ".mysqli_error($con)."</div>";
        }
    }
}

if(isset($_POST['mark_returned'])){
    @$_ExeatId = trim((string)($_POST['exeatid'] ?? ''));
    @$_ReturnNote = trim((string)($_POST['returnnote'] ?? ''));
    $_ExeatIdEsc = mysqli_real_escape_string($con, $_ExeatId);
    $_ReturnNoteEsc = mysqli_real_escape_string($con, $_ReturnNote);

    if(!$_CanManageExeat){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>You are not assigned to any exeat-managing role yet. Contact administration.</div>";
    }elseif($_ExeatId === ""){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Invalid exeat record selected for return check-in.</div>";
    }else{
        $_ReturnMetaRes = mysqli_query($con, "SELECT er.*,h.housename,su.firstname,su.surname,su.othernames
            FROM tblexeatrequest er
            INNER JOIN tblhouse h ON h.houseid=er.houseid
            INNER JOIN tblsystemuser su ON su.userid=er.userid
            WHERE er.exeatid='$_ExeatIdEsc'
              AND er.status='approved'
              AND er.actualreturndatetime IS NULL
              AND ".$_ExeatScopeSql."
            LIMIT 1");
        if(!$_ReturnMetaRes || mysqli_num_rows($_ReturnMetaRes) === 0){
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>This exeat return cannot be checked in from your current scope, or it has already been returned.</div>";
        }else{
            $_SQLReturn = mysqli_query($con, "UPDATE tblexeatrequest
                SET actualreturndatetime=NOW(), returnedby='$_TeacherIdEsc', returnnote='$_ReturnNoteEsc'
                WHERE exeatid='$_ExeatIdEsc'
                  AND status='approved'
                  AND actualreturndatetime IS NULL");
            if($_SQLReturn && mysqli_affected_rows($con) > 0){
                $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Student checked back in successfully.</div>";
            }else{
                $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to record return check-in: ".mysqli_error($con)."</div>";
            }
        }
    }
}

if($_CanManageExeat){
    $_SQLGrantStudents = mysqli_query($con, "SELECT
        su.userid,su.firstname,su.surname,su.othernames,h.housename
        FROM tblstudenthouse sh
        INNER JOIN tblsystemuser su ON su.userid=sh.userid
        INNER JOIN tblhouse h ON h.houseid=sh.houseid
        WHERE sh.status='active'
          AND su.systemtype='Student'
          AND su.status='active'
          AND ".$_StudentScopeSql."
        ORDER BY h.housename ASC, su.firstname ASC, su.surname ASC");
    if($_SQLGrantStudents){
        while($_GrantRow = mysqli_fetch_array($_SQLGrantStudents, MYSQLI_ASSOC)){
            $_StudentGrantOptions[] = $_GrantRow;
        }
    }
}

$_TeacherDisplayName = $_TeacherId;
$_TeacherRes = mysqli_query($con, "SELECT firstname,surname,othernames FROM tblsystemuser WHERE userid='$_TeacherIdEsc' LIMIT 1");
if($_TeacherRes && $_TeacherRow = mysqli_fetch_array($_TeacherRes, MYSQLI_ASSOC)){
    $_TeacherDisplayName = house_exeat_person_name($_TeacherRow);
}

$_RoleLabel = "House Exeat Desk";
if($_HasSeniorAssignment && $_SeniorAssignment){
    $_RoleLabel = trim((string)($_SeniorAssignment['designation'] ?? 'Senior House Official'));
}elseif(function_exists('house_master_dashboard_label') && house_master_dashboard_label($con, $_TeacherId) === "House Mistress Dashboard"){
    $_RoleLabel = "House Mistress";
}else{
    $_RoleLabel = "House Master";
}

$_PendingRows = array();
$_ActiveRows = array();
$_HistoryRows = array();
$_Overview = array(
    'pending' => 0,
    'active_out' => 0,
    'overdue' => 0,
    'returned_today' => 0,
    'total' => 0
);

$_SQL = mysqli_query($con, "SELECT er.*,h.housename,su.firstname,su.surname,su.othernames,
    ".$_ExpectedReturnSql." AS expected_returndatetime,
    CASE WHEN ".$_OverdueSql." THEN 1 ELSE 0 END AS is_overdue
FROM tblexeatrequest er
INNER JOIN tblhouse h ON h.houseid=er.houseid
INNER JOIN tblsystemuser su ON su.userid=er.userid
WHERE ".$_ExeatScopeSql."
ORDER BY
    CASE WHEN LOWER(COALESCE(er.exeattype,'external'))='external' THEN 0 ELSE 1 END ASC,
    CASE
        WHEN er.status='pending' THEN 0
        WHEN ".$_OverdueSql." THEN 1
        WHEN er.status='approved' AND er.actualreturndatetime IS NULL THEN 2
        WHEN er.actualreturndatetime IS NOT NULL THEN 3
        ELSE 4
    END ASC,
    er.requestedatetime DESC");

if($_SQL){
    while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
        $_Overview['total']++;
        $status = strtolower(trim((string)($row['status'] ?? '')));
        $hasReturned = trim((string)($row['actualreturndatetime'] ?? '')) !== "";
        $isOverdue = (int)($row['is_overdue'] ?? 0) === 1;
        if($status === 'pending'){
            $_PendingRows[] = $row;
            $_Overview['pending']++;
        }elseif($status === 'approved' && !$hasReturned){
            $_ActiveRows[] = $row;
            $_Overview['active_out']++;
            if($isOverdue){
                $_Overview['overdue']++;
            }
        }else{
            $_HistoryRows[] = $row;
            if($hasReturned){
                $returnedTs = strtotime((string)$row['actualreturndatetime']);
                if($returnedTs && date("Y-m-d", $returnedTs) === date("Y-m-d")){
                    $_Overview['returned_today']++;
                }
            }
        }
    }
}
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/house-master-exeat.css">
</head>
<body class="he-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <div class="he-wrap">
        <section class="he-hero">
            <div class="he-hero__copy">
                <span class="he-kicker"><i class="fa fa-random"></i> Exeat Desk</span>
                <h1>House Exeat Management</h1>
                <p>Review student exeat requests, approve them, and record return check-ins.</p>
                <div class="he-chip-row">
                    <span class="he-chip he-chip--neutral"><i class="fa fa-user-circle"></i> <?php echo house_exeat_esc($_TeacherDisplayName); ?></span>
                    <span class="he-chip he-chip--info"><i class="fa fa-shield"></i> <?php echo house_exeat_esc($_RoleLabel); ?></span>
                    <span class="he-chip he-chip--neutral"><i class="fa fa-building"></i> <?php echo house_exeat_esc($_ExeatScopeLabel); ?><?php echo $_HasSeniorAssignment ? " as ".house_exeat_esc((string)($_SeniorAssignment['designation'] ?? 'Senior House Official')) : ""; ?></span>
                </div>
            </div>
            <div class="he-stat-grid">
                <article class="he-stat">
                    <span>Pending</span>
                    <strong><?php echo (int)$_Overview['pending']; ?></strong>
                    <small>Awaiting approval or rejection</small>
                </article>
                <article class="he-stat">
                    <span>Active Out</span>
                    <strong><?php echo (int)$_Overview['active_out']; ?></strong>
                    <small>Students currently out on exeat</small>
                </article>
                <article class="he-stat">
                    <span>Overdue</span>
                    <strong><?php echo (int)$_Overview['overdue']; ?></strong>
                    <small>Return time has passed</small>
                </article>
                <article class="he-stat">
                    <span>Returned Today</span>
                    <strong><?php echo (int)$_Overview['returned_today']; ?></strong>
                    <small>Students checked in today</small>
                </article>
            </div>
        </section>

        <?php if(trim((string)$_SESSION['Message']) !== ""){ ?>
        <div class="he-flash">
            <?php echo $_SESSION['Message']; ?>
        </div>
        <?php } ?>

        <?php if(!$_CanManageExeat){ ?>
        <section class="he-panel">
            <div class="he-empty-state">You are not assigned to any exeat-managing role yet. Contact administration.</div>
        </section>
        <?php } else { ?>
        <div class="he-layout">
            <aside class="he-panel he-panel--grant">
                <div class="he-section-head">
                    <h2>Grant Exeat Directly</h2>
                    <p>Use this when you need to approve and issue exeat immediately for a student in your current scope. External exeat will still try to notify the parent right away.</p>
                </div>

                <?php if(count($_StudentGrantOptions) > 0){ ?>
                <form method="post" action="house-master-exeat.php" class="he-form">
                    <div class="he-form-grid">
                        <div class="he-field he-field--full">
                            <label for="studentid">Student</label>
                            <select name="studentid" id="studentid" required>
                                <option value="">Select Student</option>
                                <?php foreach($_StudentGrantOptions as $_GrantStudent){ ?>
                                <?php $_StudentLabel = trim($_GrantStudent['firstname']." ".$_GrantStudent['othernames']." ".$_GrantStudent['surname'])." (".$_GrantStudent['userid'].") - ".$_GrantStudent['housename']; ?>
                                <option value="<?php echo house_exeat_esc($_GrantStudent['userid']); ?>"><?php echo house_exeat_esc($_StudentLabel); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="he-field">
                            <label for="exeattype">Type</label>
                            <select name="exeattype" id="exeattype" required>
                                <option value="">Select Type</option>
                                <option value="internal">Internal</option>
                                <option value="external">External</option>
                            </select>
                        </div>

                        <div class="he-field he-field--span">
                            <label for="reason">Reason</label>
                            <input type="text" name="reason" id="reason" required>
                        </div>

                        <div class="he-field he-field--full">
                            <label for="decisionnote">Decision Note</label>
                            <input type="text" name="decisionnote" id="decisionnote" placeholder="Optional note">
                        </div>

                        <div class="he-field">
                            <label for="dateout">Date Out</label>
                            <input type="date" name="dateout" id="dateout" required>
                        </div>

                        <div class="he-field">
                            <label for="timeout">Time Out</label>
                            <input type="time" name="timeout" id="timeout" required>
                        </div>

                        <div class="he-field">
                            <label for="datereturn">Return Date</label>
                            <input type="date" name="datereturn" id="datereturn" required>
                        </div>

                        <div class="he-field">
                            <label for="timereturn">Expected Time Return</label>
                            <input type="time" name="timereturn" id="timereturn" required>
                        </div>
                    </div>

                    <button type="submit" name="grant_exeat" class="he-btn he-btn--primary">
                        <i class="fa fa-check"></i> Grant Exeat
                    </button>
                </form>
                <?php } else { ?>
                <div class="he-empty-state">No active students are assigned to your current house scope yet.</div>
                <?php } ?>
            </aside>

            <section class="he-content">
                <article class="he-panel">
                    <div class="he-section-head">
                        <h2>Pending Requests</h2>
                        <span class="he-count"><?php echo count($_PendingRows); ?></span>
                    </div>
                    <?php if(count($_PendingRows) > 0){ ?>
                    <div class="he-card-list">
                        <?php foreach($_PendingRows as $row){ ?>
                        <?php $_Type = strtolower(trim((string)($row['exeattype'] ?? 'external'))); if($_Type !== 'internal'){ $_Type = 'external'; } ?>
                        <article class="he-request-card he-request-card--pending">
                            <div class="he-request-card__top">
                                <div>
                                    <div class="he-badge-row">
                                        <span class="<?php echo house_exeat_type_class($_Type); ?>"><?php echo house_exeat_esc(ucfirst($_Type)); ?></span>
                                        <span class="<?php echo house_exeat_status_class($row); ?>"><?php echo house_exeat_esc(house_exeat_status_label($row)); ?></span>
                                    </div>
                                    <h3><?php echo house_exeat_esc(house_exeat_person_name($row)); ?></h3>
                                    <p class="he-request-meta"><?php echo house_exeat_esc($row['userid']); ?> | <?php echo house_exeat_esc($row['housename']); ?></p>
                                </div>
                                <span class="he-request-time"><?php echo house_exeat_esc(house_exeat_requested_datetime($row['requestedatetime'])); ?></span>
                            </div>

                            <dl class="he-detail-grid">
                                <div><dt>Reason</dt><dd><?php echo house_exeat_esc($row['reason']); ?></dd></div>
                                <div><dt>Departure</dt><dd><?php echo house_exeat_esc(house_exeat_format_datetime($row['dateout'], $row['timeout'])); ?></dd></div>
                                <div><dt>Return</dt><dd><?php echo house_exeat_esc(house_exeat_format_datetime($row['datereturn'], $row['timereturn'])); ?></dd></div>
                                <div><dt>Recorded By</dt><dd><?php echo house_exeat_esc($row['recordedby']); ?></dd></div>
                            </dl>

                            <form method="post" action="house-master-exeat.php" class="he-inline-form">
                                <input type="hidden" name="exeatid" value="<?php echo house_exeat_esc($row['exeatid']); ?>">
                                <select name="decision" required>
                                    <option value="approved">Approve</option>
                                    <option value="rejected">Reject</option>
                                </select>
                                <input type="text" name="decisionnote" placeholder="Decision note">
                                <button type="submit" name="decide_exeat" class="he-btn he-btn--primary">
                                    <i class="fa fa-save"></i> Save Decision
                                </button>
                            </form>
                        </article>
                        <?php } ?>
                    </div>
                    <?php } else { ?>
                    <div class="he-empty-state">No pending exeat requests are waiting for your action right now.</div>
                    <?php } ?>
                </article>

                <article class="he-panel">
                    <div class="he-section-head">
                        <h2>Active Returns</h2>
                        <span class="he-count"><?php echo count($_ActiveRows); ?></span>
                    </div>
                    <?php if(count($_ActiveRows) > 0){ ?>
                    <div class="he-card-list">
                        <?php foreach($_ActiveRows as $row){ ?>
                        <?php $_Type = strtolower(trim((string)($row['exeattype'] ?? 'external'))); if($_Type !== 'internal'){ $_Type = 'external'; } ?>
                        <?php $_ExpectedText = trim((string)($row['expected_returndatetime'] ?? '')); if($_ExpectedText === ""){ $_ExpectedText = house_exeat_format_datetime($row['datereturn'], $row['timereturn']); } ?>
                        <article class="he-request-card <?php echo ((int)($row['is_overdue'] ?? 0) === 1) ? 'he-request-card--overdue' : 'he-request-card--active'; ?>">
                            <div class="he-request-card__top">
                                <div>
                                    <div class="he-badge-row">
                                        <span class="<?php echo house_exeat_type_class($_Type); ?>"><?php echo house_exeat_esc(ucfirst($_Type)); ?></span>
                                        <span class="<?php echo house_exeat_status_class($row); ?>"><?php echo house_exeat_esc(house_exeat_status_label($row)); ?></span>
                                    </div>
                                    <h3><?php echo house_exeat_esc(house_exeat_person_name($row)); ?></h3>
                                    <p class="he-request-meta"><?php echo house_exeat_esc($row['userid']); ?> | <?php echo house_exeat_esc($row['housename']); ?></p>
                                </div>
                                <span class="he-request-time"><?php echo house_exeat_esc(house_exeat_requested_datetime($row['requestedatetime'])); ?></span>
                            </div>

                            <dl class="he-detail-grid">
                                <div><dt>Reason</dt><dd><?php echo house_exeat_esc($row['reason']); ?></dd></div>
                                <div><dt>Departure</dt><dd><?php echo house_exeat_esc(house_exeat_format_datetime($row['dateout'], $row['timeout'])); ?></dd></div>
                                <div><dt>Return</dt><dd><?php echo house_exeat_esc(house_exeat_format_datetime($row['datereturn'], $row['timereturn'])); ?></dd></div>
                                <div><dt>Recorded By</dt><dd><?php echo house_exeat_esc($row['recordedby']); ?></dd></div>
                            </dl>

                            <div class="he-note <?php echo ((int)($row['is_overdue'] ?? 0) === 1) ? 'he-note--danger' : 'he-note--info'; ?>">
                                <i class="fa <?php echo ((int)($row['is_overdue'] ?? 0) === 1) ? 'fa-exclamation-triangle' : 'fa-clock-o'; ?>"></i>
                                <span><?php echo ((int)($row['is_overdue'] ?? 0) === 1) ? 'Overdue since ' : 'Awaiting return by '; ?><?php echo house_exeat_esc($_ExpectedText); ?></span>
                            </div>

                            <form method="post" action="house-master-exeat.php" class="he-inline-form">
                                <input type="hidden" name="exeatid" value="<?php echo house_exeat_esc($row['exeatid']); ?>">
                                <input type="text" name="returnnote" placeholder="Return note (optional)">
                                <button type="submit" name="mark_returned" class="he-btn he-btn--success">
                                    <i class="fa fa-sign-in"></i> Check In Return
                                </button>
                            </form>
                        </article>
                        <?php } ?>
                    </div>
                    <?php } else { ?>
                    <div class="he-empty-state">No students are currently out on active exeat in your scope.</div>
                    <?php } ?>
                </article>

                <article class="he-panel">
                    <div class="he-section-head">
                        <h2>History</h2>
                        <span class="he-count"><?php echo count($_HistoryRows); ?></span>
                    </div>
                    <?php if(count($_HistoryRows) > 0){ ?>
                    <div class="he-card-list">
                        <?php foreach($_HistoryRows as $row){ ?>
                        <?php $_Type = strtolower(trim((string)($row['exeattype'] ?? 'external'))); if($_Type !== 'internal'){ $_Type = 'external'; } ?>
                        <article class="he-request-card he-request-card--history">
                            <div class="he-request-card__top">
                                <div>
                                    <div class="he-badge-row">
                                        <span class="<?php echo house_exeat_type_class($_Type); ?>"><?php echo house_exeat_esc(ucfirst($_Type)); ?></span>
                                        <span class="<?php echo house_exeat_status_class($row); ?>"><?php echo house_exeat_esc(house_exeat_status_label($row)); ?></span>
                                    </div>
                                    <h3><?php echo house_exeat_esc(house_exeat_person_name($row)); ?></h3>
                                    <p class="he-request-meta"><?php echo house_exeat_esc($row['userid']); ?> | <?php echo house_exeat_esc($row['housename']); ?></p>
                                </div>
                                <span class="he-request-time"><?php echo house_exeat_esc(house_exeat_requested_datetime($row['requestedatetime'])); ?></span>
                            </div>

                            <dl class="he-detail-grid">
                                <div><dt>Reason</dt><dd><?php echo house_exeat_esc($row['reason']); ?></dd></div>
                                <div><dt>Departure</dt><dd><?php echo house_exeat_esc(house_exeat_format_datetime($row['dateout'], $row['timeout'])); ?></dd></div>
                                <div><dt>Return</dt><dd><?php echo house_exeat_esc(house_exeat_format_datetime($row['datereturn'], $row['timereturn'])); ?></dd></div>
                                <div><dt>Recorded By</dt><dd><?php echo house_exeat_esc($row['recordedby']); ?></dd></div>
                            </dl>

                            <div class="he-history-meta">
                                <?php if(trim((string)($row['actualreturndatetime'] ?? '')) !== ""){ ?>
                                <div><strong>Returned:</strong> <?php echo house_exeat_esc(house_exeat_requested_datetime($row['actualreturndatetime'])); ?></div>
                                <?php } ?>
                                <?php if(trim((string)($row['returnedby'] ?? '')) !== ""){ ?>
                                <div><strong>Checked In By:</strong> <?php echo house_exeat_esc($row['returnedby']); ?></div>
                                <?php } ?>
                                <?php if(trim((string)($row['returnnote'] ?? '')) !== ""){ ?>
                                <div><strong>Return Note:</strong> <?php echo house_exeat_esc($row['returnnote']); ?></div>
                                <?php } ?>
                                <?php if(trim((string)($row['decisionnote'] ?? '')) !== ""){ ?>
                                <div><strong>Decision Note:</strong> <?php echo house_exeat_esc($row['decisionnote']); ?></div>
                                <?php } ?>
                            </div>
                        </article>
                        <?php } ?>
                    </div>
                    <?php } else { ?>
                    <div class="he-empty-state">No exeat history is available yet for your house scope.</div>
                    <?php } ?>
                </article>
            </section>
        </div>
        <?php } ?>
    </div>
</div>
</body>
</html>
