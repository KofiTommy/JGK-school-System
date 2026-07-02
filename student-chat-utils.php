<?php
include_once(__DIR__.DIRECTORY_SEPARATOR."house-master-utils.php");

if(!function_exists('student_chat_is_student')){
function student_chat_is_student(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'Student';
}
}

if(!function_exists('student_chat_is_admin')){
function student_chat_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'administrator' &&
        in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true);
}
}

if(!function_exists('student_chat_landing_page')){
function student_chat_landing_page(){
    if(function_exists('house_master_landing_page')){
        return house_master_landing_page();
    }
    return 'index.php';
}
}

if(!function_exists('student_chat_esc')){
function student_chat_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if(!function_exists('student_chat_id')){
function student_chat_id($prefix = 'SCHAT'){
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$prefix));
    if($prefix === ''){
        $prefix = 'SCHAT';
    }
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $random = '';
    for($i = 0; $i < 10; $i++){
        $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return substr($prefix, 0, 10).date('YmdHis')."_".$random;
}
}

if(!function_exists('student_chat_pair_key')){
function student_chat_pair_key($studentA, $studentB){
    $parts = array(trim((string)$studentA), trim((string)$studentB));
    sort($parts, SORT_STRING);
    return $parts[0].'|'.$parts[1];
}
}

if(!function_exists('student_chat_ensure_tables')){
function student_chat_ensure_tables($con){
    if(!$con){
        return;
    }
    if(function_exists('xschool_schema_cache_is_fresh') && xschool_schema_cache_is_fresh('schema_student_private_chat_v3', 43200)){
        return;
    }

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentchatconversation (
        conversationid VARCHAR(40) NOT NULL PRIMARY KEY,
        pairkey VARCHAR(80) NOT NULL,
        requesterid VARCHAR(30) NOT NULL,
        recipientid VARCHAR(30) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        requestedat DATETIME NOT NULL,
        respondedat DATETIME NULL,
        updatedat DATETIME NULL,
        lastmessageat DATETIME NULL,
        requestedby VARCHAR(30) NOT NULL,
        blockedby VARCHAR(30) NULL,
        blockreason VARCHAR(180) NULL,
        UNIQUE KEY uq_studentchat_pair (pairkey),
        KEY idx_studentchat_requester (requesterid, status),
        KEY idx_studentchat_recipient (recipientid, status),
        KEY idx_studentchat_updated (updatedat),
        KEY idx_studentchat_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentchatmessage (
        messageid VARCHAR(40) NOT NULL PRIMARY KEY,
        conversationid VARCHAR(40) NOT NULL,
        senderid VARCHAR(30) NOT NULL,
        messagetext TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        deletedat DATETIME NULL,
        reportedat DATETIME NULL,
        reportedby VARCHAR(30) NULL,
        reportreason VARCHAR(180) NULL,
        KEY idx_studentchatmessage_conversation (conversationid, datetimeentry),
        KEY idx_studentchatmessage_sender (senderid),
        KEY idx_studentchatmessage_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentchatblock (
        blockid VARCHAR(40) NOT NULL PRIMARY KEY,
        blockerid VARCHAR(30) NOT NULL,
        blockedid VARCHAR(30) NOT NULL,
        reason VARCHAR(180) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        updatedat DATETIME NULL,
        UNIQUE KEY uq_studentchatblock_pair (blockerid, blockedid),
        KEY idx_studentchatblock_blocked (blockedid, status),
        KEY idx_studentchatblock_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentchatreport (
        reportid VARCHAR(40) NOT NULL PRIMARY KEY,
        conversationid VARCHAR(40) NOT NULL,
        messageid VARCHAR(40) NULL,
        reporterid VARCHAR(30) NOT NULL,
        reporteduserid VARCHAR(30) NOT NULL,
        reason VARCHAR(180) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        datetimeentry DATETIME NOT NULL,
        reviewedby VARCHAR(30) NULL,
        reviewedat DATETIME NULL,
        KEY idx_studentchatreport_conversation (conversationid),
        KEY idx_studentchatreport_reporter (reporterid),
        KEY idx_studentchatreport_reported (reporteduserid),
        KEY idx_studentchatreport_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentchatsetting (
        settingid VARCHAR(40) NOT NULL PRIMARY KEY,
        chatenabled TINYINT(1) NOT NULL DEFAULT 1,
        disablenote VARCHAR(180) NULL,
        updatedat DATETIME NULL,
        updatedby VARCHAR(30) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentchatadminview (
        viewid VARCHAR(40) NOT NULL PRIMARY KEY,
        conversationid VARCHAR(40) NOT NULL,
        adminid VARCHAR(30) NOT NULL,
        action VARCHAR(40) NOT NULL DEFAULT 'view',
        reason VARCHAR(180) NULL,
        datetimeentry DATETIME NOT NULL,
        KEY idx_studentchatadminview_conversation (conversationid, datetimeentry),
        KEY idx_studentchatadminview_admin (adminid, datetimeentry),
        KEY idx_studentchatadminview_action (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "INSERT IGNORE INTO tblstudentchatsetting(
            settingid, chatenabled, disablenote, updatedat, updatedby
        ) VALUES(
            'student_private_chat', 1, '', NOW(), ''
        )");

    if(function_exists('xschool_schema_cache_mark')){
        xschool_schema_cache_mark('schema_student_private_chat_v3');
    }
}
}

if(!function_exists('student_chat_get_setting')){
function student_chat_get_setting($con){
    student_chat_ensure_tables($con);
    $setting = array(
        'settingid' => 'student_private_chat',
        'chatenabled' => 1,
        'disablenote' => '',
        'updatedat' => '',
        'updatedby' => ''
    );
    $res = mysqli_query($con, "SELECT settingid,chatenabled,disablenote,updatedat,updatedby
        FROM tblstudentchatsetting
        WHERE settingid='student_private_chat'
        LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $setting = array_merge($setting, $row);
    }
    return $setting;
}
}

if(!function_exists('student_chat_is_enabled')){
function student_chat_is_enabled($con){
    $setting = student_chat_get_setting($con);
    return (int)$setting['chatenabled'] === 1;
}
}

if(!function_exists('student_chat_update_setting')){
function student_chat_update_setting($con, $enabled, $disableNote, $updatedBy = ''){
    student_chat_ensure_tables($con);
    $enabledValue = (int)$enabled === 1 ? 1 : 0;
    $disableNote = trim((string)$disableNote);
    if(strlen($disableNote) > 180){
        $disableNote = substr($disableNote, 0, 180);
    }
    $noteEsc = mysqli_real_escape_string($con, $disableNote);
    $updatedByEsc = mysqli_real_escape_string($con, trim((string)$updatedBy));
    return mysqli_query($con, "INSERT INTO tblstudentchatsetting(
            settingid, chatenabled, disablenote, updatedat, updatedby
        ) VALUES(
            'student_private_chat', $enabledValue, '$noteEsc', NOW(), '$updatedByEsc'
        ) ON DUPLICATE KEY UPDATE
            chatenabled=VALUES(chatenabled),
            disablenote=VALUES(disablenote),
            updatedat=NOW(),
            updatedby=VALUES(updatedby)") ? true : false;
}
}

if(!function_exists('student_chat_admin_log_action')){
function student_chat_admin_log_action($con, $conversationId, $adminId, $action = 'view', $reason = ''){
    student_chat_ensure_tables($con);
    $conversationId = trim((string)$conversationId);
    $adminId = trim((string)$adminId);
    $action = trim((string)$action);
    $reason = trim((string)$reason);
    if($conversationId === '' || $adminId === ''){
        return false;
    }
    if($action === ''){
        $action = 'view';
    }
    if(strlen($action) > 40){
        $action = substr($action, 0, 40);
    }
    if(strlen($reason) > 180){
        $reason = substr($reason, 0, 180);
    }
    $viewId = student_chat_id('SAVW');
    $viewIdEsc = mysqli_real_escape_string($con, $viewId);
    $conversationEsc = mysqli_real_escape_string($con, $conversationId);
    $adminEsc = mysqli_real_escape_string($con, $adminId);
    $actionEsc = mysqli_real_escape_string($con, $action);
    $reasonEsc = mysqli_real_escape_string($con, $reason);
    return mysqli_query($con, "INSERT INTO tblstudentchatadminview(
            viewid, conversationid, adminid, action, reason, datetimeentry
        ) VALUES(
            '$viewIdEsc', '$conversationEsc', '$adminEsc', '$actionEsc', '$reasonEsc', NOW()
        )") ? true : false;
}
}

if(!function_exists('student_chat_full_name')){
function student_chat_full_name($row){
    $parts = array();
    foreach(array('firstname', 'othernames', 'surname') as $field){
        $value = isset($row[$field]) ? trim((string)$row[$field]) : '';
        if($value !== ''){
            $parts[] = $value;
        }
    }
    $name = trim(implode(' ', $parts));
    return $name !== '' ? $name : (isset($row['userid']) ? trim((string)$row['userid']) : 'Student');
}
}

if(!function_exists('student_chat_photo_src')){
function student_chat_photo_src($filename){
    $filename = trim((string)$filename);
    if($filename !== '' && file_exists(__DIR__.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.$filename)){
        return 'uploads/'.rawurlencode($filename);
    }
    return 'uploads/comm.gif';
}
}

if(!function_exists('student_chat_student_exists')){
function student_chat_student_exists($con, $studentId){
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    if($studentIdEsc === ''){
        return false;
    }
    $res = mysqli_query($con, "SELECT userid FROM tblsystemuser WHERE userid='$studentIdEsc' AND systemtype='Student' AND COALESCE(status,'active')<>'block' LIMIT 1");
    return $res && mysqli_num_rows($res) > 0;
}
}

if(!function_exists('student_chat_current_class_context')){
function student_chat_current_class_context($con, $studentId){
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $context = array('classid' => '', 'class_name' => '', 'batchid' => '', 'batch' => '');
    if($studentIdEsc === ''){
        return $context;
    }
    $res = mysqli_query($con, "SELECT cl.class_entryid,cl.batchid,ce.class_name,bh.batch
        FROM tblclass cl
        LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
        LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
        WHERE cl.userid='$studentIdEsc' AND cl.status='active'
        ORDER BY cl.datetimeentry DESC
        LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $context['classid'] = trim((string)$row['class_entryid']);
        $context['class_name'] = trim((string)$row['class_name']);
        $context['batchid'] = trim((string)$row['batchid']);
        $context['batch'] = trim((string)$row['batch']);
    }
    return $context;
}
}

if(!function_exists('student_chat_current_house_context')){
function student_chat_current_house_context($con, $studentId){
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $context = array('houseid' => '', 'housename' => '');
    if($studentIdEsc === ''){
        return $context;
    }
    $res = mysqli_query($con, "SELECT sh.houseid,h.housename
        FROM tblstudenthouse sh
        INNER JOIN tblhouse h ON h.houseid=sh.houseid
        WHERE sh.userid='$studentIdEsc' AND sh.status='active' AND h.status='active'
        ORDER BY sh.datetimeentry DESC
        LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $context['houseid'] = trim((string)$row['houseid']);
        $context['housename'] = trim((string)$row['housename']);
    }
    return $context;
}
}

if(!function_exists('student_chat_student_profile')){
function student_chat_student_profile($con, $studentId){
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    if($studentIdEsc === ''){
        return null;
    }
    $res = mysqli_query($con, "SELECT userid,firstname,othernames,surname,gender,filename,schoolindexnumber
        FROM tblsystemuser
        WHERE userid='$studentIdEsc' AND systemtype='Student'
        LIMIT 1");
    if(!$res || !($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return null;
    }
    $classContext = student_chat_current_class_context($con, $studentId);
    $houseContext = student_chat_current_house_context($con, $studentId);
    $row['display_name'] = student_chat_full_name($row);
    $row['photo_src'] = student_chat_photo_src(isset($row['filename']) ? $row['filename'] : '');
    $row['classid'] = $classContext['classid'];
    $row['class_name'] = $classContext['class_name'];
    $row['batchid'] = $classContext['batchid'];
    $row['batch'] = $classContext['batch'];
    $row['houseid'] = $houseContext['houseid'];
    $row['housename'] = $houseContext['housename'];
    return $row;
}
}

if(!function_exists('student_chat_pair_has_block')){
function student_chat_pair_has_block($con, $studentA, $studentB){
    $aEsc = mysqli_real_escape_string($con, trim((string)$studentA));
    $bEsc = mysqli_real_escape_string($con, trim((string)$studentB));
    if($aEsc === '' || $bEsc === ''){
        return false;
    }
    $res = mysqli_query($con, "SELECT blockerid FROM tblstudentchatblock
        WHERE status='active'
          AND ((blockerid='$aEsc' AND blockedid='$bEsc') OR (blockerid='$bEsc' AND blockedid='$aEsc'))
        LIMIT 1");
    return $res && mysqli_num_rows($res) > 0;
}
}

if(!function_exists('student_chat_find_conversation_by_pair')){
function student_chat_find_conversation_by_pair($con, $studentA, $studentB){
    $pairKeyEsc = mysqli_real_escape_string($con, student_chat_pair_key($studentA, $studentB));
    if($pairKeyEsc === '|'){
        return null;
    }
    $res = mysqli_query($con, "SELECT * FROM tblstudentchatconversation WHERE pairkey='$pairKeyEsc' LIMIT 1");
    return ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) ? $row : null;
}
}

if(!function_exists('student_chat_relationship_meta')){
function student_chat_relationship_meta($con, $studentId, $otherId){
    $studentId = trim((string)$studentId);
    $otherId = trim((string)$otherId);
    $meta = array('state' => 'none', 'label' => 'Send Chat Request', 'conversationid' => '', 'status' => '');
    if($studentId === '' || $otherId === ''){
        return $meta;
    }
    $studentEsc = mysqli_real_escape_string($con, $studentId);
    $otherEsc = mysqli_real_escape_string($con, $otherId);
    $blockRes = mysqli_query($con, "SELECT blockerid FROM tblstudentchatblock
        WHERE status='active'
          AND ((blockerid='$studentEsc' AND blockedid='$otherEsc') OR (blockerid='$otherEsc' AND blockedid='$studentEsc'))
        LIMIT 1");
    if($blockRes && ($blockRow = mysqli_fetch_array($blockRes, MYSQLI_ASSOC))){
        if((string)$blockRow['blockerid'] === $studentId){
            return array('state' => 'blocked_by_me', 'label' => 'Blocked', 'conversationid' => '', 'status' => 'blocked');
        }
        return array('state' => 'blocked_me', 'label' => 'Unavailable', 'conversationid' => '', 'status' => 'blocked');
    }

    $conversation = student_chat_find_conversation_by_pair($con, $studentId, $otherId);
    if(!$conversation){
        return $meta;
    }
    $status = strtolower(trim((string)$conversation['status']));
    $meta['conversationid'] = trim((string)$conversation['conversationid']);
    $meta['status'] = $status;
    if($status === 'accepted'){
        $meta['state'] = 'accepted';
        $meta['label'] = 'Open Chat';
    }elseif($status === 'pending'){
        if((string)$conversation['requesterid'] === $studentId){
            $meta['state'] = 'outgoing_pending';
            $meta['label'] = 'Request Sent';
        }else{
            $meta['state'] = 'incoming_pending';
            $meta['label'] = 'Respond to Request';
        }
    }elseif($status === 'declined'){
        $meta['state'] = 'declined';
        $meta['label'] = 'Request Again';
    }elseif($status === 'cancelled'){
        $meta['state'] = 'cancelled';
        $meta['label'] = 'Send Chat Request';
    }else{
        $meta['state'] = $status !== '' ? $status : 'none';
        $meta['label'] = ucfirst($meta['state']);
    }
    return $meta;
}
}

if(!function_exists('student_chat_search_students')){
function student_chat_search_students($con, $studentId, $query, $scope = 'all', $limit = 30){
    $studentId = trim((string)$studentId);
    $query = trim((string)$query);
    $scope = trim((string)$scope);
    $limit = max(1, min(50, (int)$limit));
    if($studentId === ''){
        return array();
    }
    $currentProfile = student_chat_student_profile($con, $studentId);
    if(!$currentProfile){
        return array();
    }
    if($query === '' && $scope === 'all'){
        return array();
    }
    if($query !== '' && strlen($query) < 2){
        return array();
    }

    $studentEsc = mysqli_real_escape_string($con, $studentId);
    $where = array(
        "su.systemtype='Student'",
        "su.userid<>'$studentEsc'",
        "COALESCE(su.status,'active')<>'block'",
        "NOT EXISTS (
            SELECT 1 FROM tblstudentchatblock cb
            WHERE cb.status='active'
              AND ((cb.blockerid='$studentEsc' AND cb.blockedid=su.userid) OR (cb.blockedid='$studentEsc' AND cb.blockerid=su.userid))
        )"
    );

    if($query !== ''){
        $like = mysqli_real_escape_string($con, '%'.$query.'%');
        $where[] = "(su.userid LIKE '$like'
            OR su.firstname LIKE '$like'
            OR su.surname LIKE '$like'
            OR su.othernames LIKE '$like'
            OR su.schoolindexnumber LIKE '$like')";
    }

    if($scope === 'my_class'){
        $classIdEsc = mysqli_real_escape_string($con, (string)$currentProfile['classid']);
        $batchIdEsc = mysqli_real_escape_string($con, (string)$currentProfile['batchid']);
        if($classIdEsc === '' || $batchIdEsc === ''){
            return array();
        }
        $where[] = "EXISTS (
            SELECT 1 FROM tblclass cl
            WHERE cl.userid=su.userid
              AND cl.status='active'
              AND cl.class_entryid='$classIdEsc'
              AND cl.batchid='$batchIdEsc'
        )";
    }elseif($scope === 'my_house'){
        $houseIdEsc = mysqli_real_escape_string($con, (string)$currentProfile['houseid']);
        if($houseIdEsc === ''){
            return array();
        }
        $where[] = "EXISTS (
            SELECT 1 FROM tblstudenthouse sh
            WHERE sh.userid=su.userid
              AND sh.status='active'
              AND sh.houseid='$houseIdEsc'
        )";
    }

    $sql = "SELECT su.userid,su.firstname,su.othernames,su.surname,su.gender,su.filename,su.schoolindexnumber
        FROM tblsystemuser su
        WHERE ".implode(' AND ', $where)."
        ORDER BY su.firstname ASC, su.surname ASC
        LIMIT $limit";
    $rows = array();
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $profile = student_chat_student_profile($con, $row['userid']);
            if(!$profile){
                continue;
            }
            $profile['relationship'] = student_chat_relationship_meta($con, $studentId, $profile['userid']);
            $rows[] = $profile;
        }
    }
    return $rows;
}
}

if(!function_exists('student_chat_get_conversation_for_student')){
function student_chat_get_conversation_for_student($con, $conversationId, $studentId){
    $conversationIdEsc = mysqli_real_escape_string($con, trim((string)$conversationId));
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    if($conversationIdEsc === '' || $studentIdEsc === ''){
        return null;
    }
    $res = mysqli_query($con, "SELECT *
        FROM tblstudentchatconversation
        WHERE conversationid='$conversationIdEsc'
          AND (requesterid='$studentIdEsc' OR recipientid='$studentIdEsc')
        LIMIT 1");
    if(!$res || !($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return null;
    }
    $otherId = ((string)$row['requesterid'] === trim((string)$studentId)) ? $row['recipientid'] : $row['requesterid'];
    $row['otherid'] = $otherId;
    $row['other_profile'] = student_chat_student_profile($con, $otherId);
    return $row;
}
}

if(!function_exists('student_chat_list_conversations')){
function student_chat_list_conversations($con, $studentId, $limit = 40){
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $limit = max(1, min(80, (int)$limit));
    $rows = array();
    if($studentIdEsc === ''){
        return $rows;
    }
    $sql = "SELECT c.*,
            CASE WHEN c.requesterid='$studentIdEsc' THEN c.recipientid ELSE c.requesterid END AS otherid,
            (SELECT m.messagetext FROM tblstudentchatmessage m
             WHERE m.conversationid=c.conversationid AND m.status<>'deleted'
             ORDER BY m.datetimeentry DESC LIMIT 1) AS last_message,
            (SELECT m.datetimeentry FROM tblstudentchatmessage m
             WHERE m.conversationid=c.conversationid AND m.status<>'deleted'
             ORDER BY m.datetimeentry DESC LIMIT 1) AS last_message_at
        FROM tblstudentchatconversation c
        WHERE c.requesterid='$studentIdEsc' OR c.recipientid='$studentIdEsc'
        ORDER BY COALESCE(c.lastmessageat, c.updatedat, c.requestedat) DESC
        LIMIT $limit";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row['other_profile'] = student_chat_student_profile($con, $row['otherid']);
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('student_chat_pending_inbound_count')){
function student_chat_pending_inbound_count($con, $studentId){
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    if($studentIdEsc === ''){
        return 0;
    }
    $res = mysqli_query($con, "SELECT COUNT(*) AS total_pending
        FROM tblstudentchatconversation
        WHERE recipientid='$studentIdEsc' AND status='pending'");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return (int)$row['total_pending'];
    }
    return 0;
}
}

if(!function_exists('student_chat_status_label')){
function student_chat_status_label($status){
    $status = strtolower(trim((string)$status));
    $map = array(
        'pending' => 'Pending Approval',
        'accepted' => 'Active Chat',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
        'blocked' => 'Blocked'
    );
    return isset($map[$status]) ? $map[$status] : 'Open';
}
}

if(!function_exists('student_chat_request')){
function student_chat_request($con, $requesterId, $recipientId){
    $requesterId = trim((string)$requesterId);
    $recipientId = trim((string)$recipientId);
    if($requesterId === '' || $recipientId === '' || $requesterId === $recipientId){
        return array('success' => false, 'message' => 'Select a valid student.', 'conversationid' => '');
    }
    if(!student_chat_student_exists($con, $recipientId)){
        return array('success' => false, 'message' => 'The selected student could not be found.', 'conversationid' => '');
    }
    if(student_chat_pair_has_block($con, $requesterId, $recipientId)){
        return array('success' => false, 'message' => 'Chat is not available for this student.', 'conversationid' => '');
    }

    $existing = student_chat_find_conversation_by_pair($con, $requesterId, $recipientId);
    if($existing){
        $status = strtolower(trim((string)$existing['status']));
        if($status === 'accepted'){
            return array('success' => true, 'message' => 'Chat already active.', 'conversationid' => $existing['conversationid']);
        }
        if($status === 'pending'){
            return array('success' => true, 'message' => 'Chat request is already pending.', 'conversationid' => $existing['conversationid']);
        }
        if($status === 'blocked'){
            return array('success' => false, 'message' => 'Chat is blocked for this student.', 'conversationid' => '');
        }
        $conversationEsc = mysqli_real_escape_string($con, $existing['conversationid']);
        $requesterEsc = mysqli_real_escape_string($con, $requesterId);
        $recipientEsc = mysqli_real_escape_string($con, $recipientId);
        $updated = mysqli_query($con, "UPDATE tblstudentchatconversation
            SET requesterid='$requesterEsc',
                recipientid='$recipientEsc',
                requestedby='$requesterEsc',
                status='pending',
                requestedat=NOW(),
                respondedat=NULL,
                updatedat=NOW(),
                blockedby=NULL,
                blockreason=NULL
            WHERE conversationid='$conversationEsc'
            LIMIT 1");
        return array('success' => (bool)$updated, 'message' => $updated ? 'Chat request sent.' : 'Chat request could not be sent.', 'conversationid' => $existing['conversationid']);
    }

    $conversationId = student_chat_id('SCONV');
    $conversationEsc = mysqli_real_escape_string($con, $conversationId);
    $pairKeyEsc = mysqli_real_escape_string($con, student_chat_pair_key($requesterId, $recipientId));
    $requesterEsc = mysqli_real_escape_string($con, $requesterId);
    $recipientEsc = mysqli_real_escape_string($con, $recipientId);
    $inserted = mysqli_query($con, "INSERT INTO tblstudentchatconversation(
            conversationid, pairkey, requesterid, recipientid, status, requestedat, updatedat, requestedby
        ) VALUES(
            '$conversationEsc', '$pairKeyEsc', '$requesterEsc', '$recipientEsc', 'pending', NOW(), NOW(), '$requesterEsc'
        )");
    return array('success' => (bool)$inserted, 'message' => $inserted ? 'Chat request sent.' : 'Chat request could not be sent.', 'conversationid' => $inserted ? $conversationId : '');
}
}

if(!function_exists('student_chat_accept')){
function student_chat_accept($con, $conversationId, $studentId){
    $conversationIdEsc = mysqli_real_escape_string($con, trim((string)$conversationId));
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $updated = mysqli_query($con, "UPDATE tblstudentchatconversation
        SET status='accepted', respondedat=NOW(), updatedat=NOW()
        WHERE conversationid='$conversationIdEsc'
          AND recipientid='$studentIdEsc'
          AND status='pending'
        LIMIT 1");
    return $updated && mysqli_affected_rows($con) > 0;
}
}

if(!function_exists('student_chat_decline')){
function student_chat_decline($con, $conversationId, $studentId){
    $conversationIdEsc = mysqli_real_escape_string($con, trim((string)$conversationId));
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $updated = mysqli_query($con, "UPDATE tblstudentchatconversation
        SET status='declined', respondedat=NOW(), updatedat=NOW()
        WHERE conversationid='$conversationIdEsc'
          AND recipientid='$studentIdEsc'
          AND status='pending'
        LIMIT 1");
    return $updated && mysqli_affected_rows($con) > 0;
}
}

if(!function_exists('student_chat_cancel')){
function student_chat_cancel($con, $conversationId, $studentId){
    $conversationIdEsc = mysqli_real_escape_string($con, trim((string)$conversationId));
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $updated = mysqli_query($con, "UPDATE tblstudentchatconversation
        SET status='cancelled', updatedat=NOW()
        WHERE conversationid='$conversationIdEsc'
          AND requesterid='$studentIdEsc'
          AND status='pending'
        LIMIT 1");
    return $updated && mysqli_affected_rows($con) > 0;
}
}

if(!function_exists('student_chat_send_message')){
function student_chat_send_message($con, $conversationId, $senderId, $messageText){
    $conversation = student_chat_get_conversation_for_student($con, $conversationId, $senderId);
    $messageText = trim((string)$messageText);
    if(!$conversation || strtolower(trim((string)$conversation['status'])) !== 'accepted'){
        return array('success' => false, 'message' => 'This chat is not active yet.');
    }
    if($messageText === ''){
        return array('success' => false, 'message' => 'Type a message before sending.');
    }
    if(strlen($messageText) > 1200){
        $messageText = substr($messageText, 0, 1200);
    }
    if(student_chat_pair_has_block($con, $senderId, $conversation['otherid'])){
        return array('success' => false, 'message' => 'This chat is blocked.');
    }
    $messageId = student_chat_id('SMSG');
    $messageIdEsc = mysqli_real_escape_string($con, $messageId);
    $conversationEsc = mysqli_real_escape_string($con, $conversationId);
    $senderEsc = mysqli_real_escape_string($con, trim((string)$senderId));
    $messageEsc = mysqli_real_escape_string($con, $messageText);
    $inserted = mysqli_query($con, "INSERT INTO tblstudentchatmessage(
            messageid, conversationid, senderid, messagetext, status, datetimeentry
        ) VALUES(
            '$messageIdEsc', '$conversationEsc', '$senderEsc', '$messageEsc', 'active', NOW()
        )");
    if($inserted){
        mysqli_query($con, "UPDATE tblstudentchatconversation
            SET lastmessageat=NOW(), updatedat=NOW()
            WHERE conversationid='$conversationEsc'
            LIMIT 1");
    }
    return array('success' => (bool)$inserted, 'message' => $inserted ? 'Message sent.' : 'Message could not be sent.');
}
}

if(!function_exists('student_chat_list_messages')){
function student_chat_list_messages($con, $conversationId, $studentId, $limit = 120){
    $conversation = student_chat_get_conversation_for_student($con, $conversationId, $studentId);
    if(!$conversation){
        return array();
    }
    $conversationEsc = mysqli_real_escape_string($con, trim((string)$conversationId));
    $limit = max(1, min(200, (int)$limit));
    $rows = array();
    $res = mysqli_query($con, "SELECT m.*,su.firstname,su.othernames,su.surname
        FROM tblstudentchatmessage m
        INNER JOIN tblsystemuser su ON su.userid=m.senderid
        WHERE m.conversationid='$conversationEsc'
          AND m.status<>'deleted'
        ORDER BY m.datetimeentry ASC
        LIMIT $limit");
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row['sender_name'] = student_chat_full_name($row);
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('student_chat_block_student')){
function student_chat_block_student($con, $blockerId, $blockedId, $reason = ''){
    $blockerId = trim((string)$blockerId);
    $blockedId = trim((string)$blockedId);
    $reason = trim((string)$reason);
    if($blockerId === '' || $blockedId === '' || $blockerId === $blockedId){
        return false;
    }
    if(strlen($reason) > 180){
        $reason = substr($reason, 0, 180);
    }
    $blockId = student_chat_id('SBLK');
    $blockIdEsc = mysqli_real_escape_string($con, $blockId);
    $blockerEsc = mysqli_real_escape_string($con, $blockerId);
    $blockedEsc = mysqli_real_escape_string($con, $blockedId);
    $reasonEsc = mysqli_real_escape_string($con, $reason);
    $saved = mysqli_query($con, "INSERT INTO tblstudentchatblock(
            blockid, blockerid, blockedid, reason, status, datetimeentry, updatedat
        ) VALUES(
            '$blockIdEsc', '$blockerEsc', '$blockedEsc', '$reasonEsc', 'active', NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            reason=VALUES(reason),
            status='active',
            updatedat=NOW()");
    if($saved){
        $pairKeyEsc = mysqli_real_escape_string($con, student_chat_pair_key($blockerId, $blockedId));
        mysqli_query($con, "UPDATE tblstudentchatconversation
            SET status='blocked', blockedby='$blockerEsc', blockreason='$reasonEsc', updatedat=NOW()
            WHERE pairkey='$pairKeyEsc'
            LIMIT 1");
    }
    return (bool)$saved;
}
}

if(!function_exists('student_chat_report')){
function student_chat_report($con, $conversationId, $reporterId, $reason, $messageId = ''){
    $conversation = student_chat_get_conversation_for_student($con, $conversationId, $reporterId);
    $reason = trim((string)$reason);
    $messageId = trim((string)$messageId);
    if(!$conversation || $reason === ''){
        return false;
    }
    if(strlen($reason) > 180){
        $reason = substr($reason, 0, 180);
    }

    $reportedUserId = trim((string)$conversation['otherid']);
    if($messageId !== ''){
        $messageIdEscCheck = mysqli_real_escape_string($con, $messageId);
        $conversationEscCheck = mysqli_real_escape_string($con, $conversationId);
        $msgRes = mysqli_query($con, "SELECT senderid FROM tblstudentchatmessage
            WHERE messageid='$messageIdEscCheck'
              AND conversationid='$conversationEscCheck'
            LIMIT 1");
        if($msgRes && ($msgRow = mysqli_fetch_array($msgRes, MYSQLI_ASSOC))){
            $reportedUserId = trim((string)$msgRow['senderid']);
        }
    }
    if($reportedUserId === trim((string)$reporterId)){
        $reportedUserId = trim((string)$conversation['otherid']);
    }

    $reportId = student_chat_id('SRPT');
    $reportIdEsc = mysqli_real_escape_string($con, $reportId);
    $conversationEsc = mysqli_real_escape_string($con, $conversationId);
    $messageEsc = mysqli_real_escape_string($con, $messageId);
    $reporterEsc = mysqli_real_escape_string($con, trim((string)$reporterId));
    $reportedEsc = mysqli_real_escape_string($con, $reportedUserId);
    $reasonEsc = mysqli_real_escape_string($con, $reason);
    $messageSql = $messageEsc !== '' ? "'$messageEsc'" : "NULL";
    $saved = mysqli_query($con, "INSERT INTO tblstudentchatreport(
            reportid, conversationid, messageid, reporterid, reporteduserid, reason, status, datetimeentry
        ) VALUES(
            '$reportIdEsc', '$conversationEsc', $messageSql, '$reporterEsc', '$reportedEsc', '$reasonEsc', 'open', NOW()
        )");
    if($saved && $messageEsc !== ''){
        mysqli_query($con, "UPDATE tblstudentchatmessage
            SET status='reported', reportedat=NOW(), reportedby='$reporterEsc', reportreason='$reasonEsc'
            WHERE messageid='$messageEsc'
              AND conversationid='$conversationEsc'
            LIMIT 1");
    }
    return (bool)$saved;
}
}
?>
