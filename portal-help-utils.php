<?php
include_once(__DIR__.DIRECTORY_SEPARATOR."audit_notifications.php");

if(!function_exists('ensure_portal_help_request_table')){
function ensure_portal_help_request_table($con){
    static $done = false;
    if($done || !$con){
        return;
    }
    $done = true;
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblportalhelprequest (
        requestid VARCHAR(40) NOT NULL PRIMARY KEY,
        requestername VARCHAR(150) NOT NULL,
        requesterrole VARCHAR(30) NOT NULL DEFAULT 'visitor',
        contactphone VARCHAR(40) NULL,
        contactemail VARCHAR(120) NULL,
        helptopic VARCHAR(40) NOT NULL DEFAULT 'general',
        helpmessage TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        adminnote VARCHAR(255) NULL,
        sourcepage VARCHAR(120) NULL,
        ipaddress VARCHAR(64) NULL,
        useragent VARCHAR(255) NULL,
        branchid VARCHAR(30) NOT NULL DEFAULT '',
        requestedat DATETIME NOT NULL,
        updatedat DATETIME NOT NULL,
        updatedby VARCHAR(40) NULL,
        INDEX idx_portalhelp_status (status),
        INDEX idx_portalhelp_requested (requestedat),
        INDEX idx_portalhelp_branch (branchid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
}

if(!function_exists('portal_help_generate_id')){
function portal_help_generate_id($prefix = 'HELP_'){
    $prefix = trim((string)$prefix);
    if($prefix === ''){
        $prefix = 'HELP_';
    }
    try{
        return $prefix.strtoupper(bin2hex(random_bytes(6)));
    }catch(Exception $e){
        return $prefix.strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 12));
    }
}
}

if(!function_exists('portal_help_normalize_role')){
function portal_help_normalize_role($role){
    $role = strtolower(trim((string)$role));
    $allowed = array('visitor', 'parent', 'student', 'teacher', 'staff', 'applicant', 'other');
    return in_array($role, $allowed, true) ? $role : 'visitor';
}
}

if(!function_exists('portal_help_normalize_topic')){
function portal_help_normalize_topic($topic){
    $topic = strtolower(trim((string)$topic));
    $allowed = array('login', 'admission', 'results', 'fees', 'technical', 'general', 'other');
    return in_array($topic, $allowed, true) ? $topic : 'general';
}
}

if(!function_exists('portal_help_role_label')){
function portal_help_role_label($role){
    $map = array(
        'visitor' => 'Visitor',
        'parent' => 'Parent',
        'student' => 'Student',
        'teacher' => 'Teacher',
        'staff' => 'Staff',
        'applicant' => 'Applicant',
        'other' => 'Other'
    );
    $role = portal_help_normalize_role($role);
    return isset($map[$role]) ? $map[$role] : 'Visitor';
}
}

if(!function_exists('portal_help_topic_label')){
function portal_help_topic_label($topic){
    $map = array(
        'login' => 'Login Problem',
        'admission' => 'Admission',
        'results' => 'Results',
        'fees' => 'Fees / Payments',
        'technical' => 'Technical Issue',
        'general' => 'General Help',
        'other' => 'Other'
    );
    $topic = portal_help_normalize_topic($topic);
    return isset($map[$topic]) ? $map[$topic] : 'General Help';
}
}

if(!function_exists('portal_help_status_label')){
function portal_help_status_label($status){
    $map = array(
        'open' => 'Open',
        'contacted' => 'Contacted',
        'resolved' => 'Resolved'
    );
    $status = strtolower(trim((string)$status));
    return isset($map[$status]) ? $map[$status] : 'Open';
}
}

if(!function_exists('portal_help_status_badge_class')){
function portal_help_status_badge_class($status){
    $status = strtolower(trim((string)$status));
    if($status === 'resolved'){
        return 'portal-help-badge portal-help-badge--resolved';
    }
    if($status === 'contacted'){
        return 'portal-help-badge portal-help-badge--contacted';
    }
    return 'portal-help-badge portal-help-badge--open';
}
}

if(!function_exists('portal_help_log_request_notification')){
function portal_help_log_request_notification($con, $requestId, $data = array()){
    if(!$con || trim((string)$requestId) === ''){
        return false;
    }
    ensureSystemChangeLogTable($con);

    $requesterName = trim((string)(isset($data['requestername']) ? $data['requestername'] : ''));
    $requesterRole = portal_help_role_label(isset($data['requesterrole']) ? $data['requesterrole'] : '');
    $topicLabel = portal_help_topic_label(isset($data['helptopic']) ? $data['helptopic'] : '');
    $contactPhone = trim((string)(isset($data['contactphone']) ? $data['contactphone'] : ''));
    $helpMessage = trim((string)(isset($data['helpmessage']) ? $data['helpmessage'] : ''));
    $details = ($requesterName !== '' ? $requesterName : 'A visitor')." sent a portal help request";
    if($requesterRole !== ''){
        $details .= " (".$requesterRole.")";
    }
    if($topicLabel !== ''){
        $details .= " about ".$topicLabel;
    }
    if($contactPhone !== ''){
        $details .= ". Phone: ".$contactPhone;
    }
    if($helpMessage !== ''){
        $details .= ". Message: ".substr($helpMessage, 0, 180);
    }

    $requestIdEsc = mysqli_real_escape_string($con, trim((string)$requestId));
    $actorNameEsc = mysqli_real_escape_string($con, $requesterName !== '' ? $requesterName : 'Portal Visitor');
    $detailsEsc = mysqli_real_escape_string($con, $details);
    $pageNameEsc = mysqli_real_escape_string($con, 'index.php');
    $ipAddressEsc = mysqli_real_escape_string($con, isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '');

    return mysqli_query($con, "INSERT INTO tblsystemchangelog
        (actor_userid, actor_name, actor_type, action_type, target_userid, details, page_name, ip_address, datetimeentry, status)
        VALUES('', '$actorNameEsc', 'Portal', 'PORTAL_HELP_REQUEST', '$requestIdEsc', '$detailsEsc', '$pageNameEsc', '$ipAddressEsc', NOW(), 'unread')") ? true : false;
}
}

if(!function_exists('portal_help_create_request')){
function portal_help_create_request($con, $data){
    if(!$con){
        return false;
    }
    ensure_portal_help_request_table($con);

    $requestId = portal_help_generate_id('HELP_');
    $requesterName = trim((string)(isset($data['requestername']) ? $data['requestername'] : ''));
    $requesterRole = portal_help_normalize_role(isset($data['requesterrole']) ? $data['requesterrole'] : '');
    $contactPhone = trim((string)(isset($data['contactphone']) ? $data['contactphone'] : ''));
    $contactEmail = trim((string)(isset($data['contactemail']) ? $data['contactemail'] : ''));
    $helpTopic = portal_help_normalize_topic(isset($data['helptopic']) ? $data['helptopic'] : '');
    $helpMessage = trim((string)(isset($data['helpmessage']) ? $data['helpmessage'] : ''));
    $sourcePage = trim((string)(isset($data['sourcepage']) ? $data['sourcepage'] : 'index.php'));
    $ipAddress = trim((string)(isset($data['ipaddress']) ? $data['ipaddress'] : ''));
    $userAgent = trim((string)(isset($data['useragent']) ? $data['useragent'] : ''));
    $branchId = trim((string)(isset($data['branchid']) ? $data['branchid'] : ''));

    $stmt = mysqli_prepare($con, "INSERT INTO tblportalhelprequest(
        requestid, requestername, requesterrole, contactphone, contactemail, helptopic,
        helpmessage, status, adminnote, sourcepage, ipaddress, useragent, branchid,
        requestedat, updatedat, updatedby
    ) VALUES(
        ?, ?, ?, ?, ?, ?,
        ?, 'open', NULL, ?, ?, ?, ?,
        NOW(), NOW(), NULL
    )");
    if(!$stmt){
        return false;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssssssssss",
        $requestId,
        $requesterName,
        $requesterRole,
        $contactPhone,
        $contactEmail,
        $helpTopic,
        $helpMessage,
        $sourcePage,
        $ipAddress,
        $userAgent,
        $branchId
    );
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if(!$saved){
        return false;
    }

    portal_help_log_request_notification($con, $requestId, array(
        'requestername' => $requesterName,
        'requesterrole' => $requesterRole,
        'contactphone' => $contactPhone,
        'helptopic' => $helpTopic,
        'helpmessage' => $helpMessage
    ));
    return $requestId;
}
}

if(!function_exists('portal_help_request_summary')){
function portal_help_request_summary($con){
    $summary = array(
        'total' => 0,
        'open' => 0,
        'contacted' => 0,
        'resolved' => 0
    );
    if(!$con){
        return $summary;
    }
    ensure_portal_help_request_table($con);
    $res = mysqli_query($con, "SELECT status, COUNT(*) AS total_rows
        FROM tblportalhelprequest
        GROUP BY status");
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $status = strtolower(trim((string)$row['status']));
            $count = (int)$row['total_rows'];
            $summary['total'] += $count;
            if(isset($summary[$status])){
                $summary[$status] = $count;
            }
        }
    }
    return $summary;
}
}

if(!function_exists('portal_help_recent_requests')){
function portal_help_recent_requests($con, $limit = 20){
    $rows = array();
    if(!$con){
        return $rows;
    }
    ensure_portal_help_request_table($con);
    $limit = max(1, (int)$limit);
    $res = mysqli_query($con, "SELECT *
        FROM tblportalhelprequest
        ORDER BY
            CASE status
                WHEN 'open' THEN 0
                WHEN 'contacted' THEN 1
                WHEN 'resolved' THEN 2
                ELSE 3
            END,
            requestedat DESC
        LIMIT ".$limit);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('portal_help_update_request')){
function portal_help_update_request($con, $requestId, $status, $adminNote = '', $updatedBy = ''){
    if(!$con || trim((string)$requestId) === ''){
        return false;
    }
    ensure_portal_help_request_table($con);
    $allowedStatuses = array('open', 'contacted', 'resolved');
    $status = strtolower(trim((string)$status));
    if(!in_array($status, $allowedStatuses, true)){
        $status = 'open';
    }
    $requestIdEsc = mysqli_real_escape_string($con, trim((string)$requestId));
    $statusEsc = mysqli_real_escape_string($con, $status);
    $noteEsc = mysqli_real_escape_string($con, trim((string)$adminNote));
    $updatedByEsc = trim((string)$updatedBy) !== '' ? "'".mysqli_real_escape_string($con, trim((string)$updatedBy))."'" : "NULL";
    return mysqli_query($con, "UPDATE tblportalhelprequest SET
        status='$statusEsc',
        adminnote='$noteEsc',
        updatedat=NOW(),
        updatedby=$updatedByEsc
        WHERE requestid='$requestIdEsc'
        LIMIT 1");
}
}
?>
