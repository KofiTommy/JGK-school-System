<?php

if(!function_exists('voting_column_exists')){
function voting_column_exists($con, $table, $column){
    static $cache = array();
    $key = strtolower(trim((string)$table)).'.'.strtolower(trim((string)$column));
    if(isset($cache[$key])){
        return $cache[$key];
    }
    $tableEsc = mysqli_real_escape_string($con, trim((string)$table));
    $columnEsc = mysqli_real_escape_string($con, trim((string)$column));
    $res = @mysqli_query($con, "SHOW COLUMNS FROM `$tableEsc` LIKE '$columnEsc'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}
}

if(!function_exists('ensure_voting_tables')){
function ensure_voting_tables($con){
    static $done = false;
    if($done || !$con){
        return;
    }
    $done = true;

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblvotingcontest (
        contestid VARCHAR(60) NOT NULL,
        branchid VARCHAR(60) NOT NULL,
        title VARCHAR(180) NOT NULL,
        tagline VARCHAR(220) NOT NULL DEFAULT '',
        description TEXT NULL,
        contesttype VARCHAR(30) NOT NULL DEFAULT 'pageantry',
        votereligibility VARCHAR(20) NOT NULL DEFAULT 'both',
        livemode VARCHAR(20) NOT NULL DEFAULT 'full',
        pricepervote DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        minvotes INT NOT NULL DEFAULT 1,
        maxvotes INT NOT NULL DEFAULT 100,
        startsat DATETIME NULL DEFAULT NULL,
        endsat DATETIME NULL DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        recordedby VARCHAR(60) NOT NULL DEFAULT '',
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (contestid),
        KEY idx_votingcontest_branch (branchid),
        KEY idx_votingcontest_status (status),
        KEY idx_votingcontest_window (startsat, endsat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblvotingcandidate (
        candidateid VARCHAR(60) NOT NULL,
        contestid VARCHAR(60) NOT NULL,
        candidatename VARCHAR(180) NOT NULL,
        contestantnumber VARCHAR(30) NOT NULL DEFAULT '',
        classlabel VARCHAR(120) NOT NULL DEFAULT '',
        houselabel VARCHAR(120) NOT NULL DEFAULT '',
        gender VARCHAR(20) NOT NULL DEFAULT '',
        photofile VARCHAR(255) NOT NULL DEFAULT '',
        videofile VARCHAR(255) NOT NULL DEFAULT '',
        videolink VARCHAR(255) NOT NULL DEFAULT '',
        slogan VARCHAR(220) NOT NULL DEFAULT '',
        profiletext TEXT NULL,
        displayorder INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        recordedby VARCHAR(60) NOT NULL DEFAULT '',
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (candidateid),
        KEY idx_votingcandidate_contest (contestid),
        KEY idx_votingcandidate_status (status),
        KEY idx_votingcandidate_order (contestid, displayorder, candidatename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblvotingcampaignhistory (
        historyid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contestid VARCHAR(60) NOT NULL,
        candidateid VARCHAR(60) NOT NULL,
        candidatename VARCHAR(180) NOT NULL DEFAULT '',
        actiontype VARCHAR(40) NOT NULL DEFAULT '',
        actiontitle VARCHAR(180) NOT NULL DEFAULT '',
        actiondetails TEXT NULL,
        recordedby VARCHAR(60) NOT NULL DEFAULT '',
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (historyid),
        KEY idx_votinghistory_contest (contestid),
        KEY idx_votinghistory_candidate (candidateid),
        KEY idx_votinghistory_action (actiontype),
        KEY idx_votinghistory_datetime (datetimeentry)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblvotingpayment (
        paymentid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contestid VARCHAR(60) NOT NULL,
        candidateid VARCHAR(60) NOT NULL,
        branchid VARCHAR(60) NOT NULL,
        voterid VARCHAR(60) NOT NULL DEFAULT '',
        votersystemtype VARCHAR(20) NOT NULL DEFAULT '',
        votername VARCHAR(180) NOT NULL DEFAULT '',
        voteremail VARCHAR(180) NOT NULL DEFAULT '',
        votermobile VARCHAR(60) NOT NULL DEFAULT '',
        votequantity INT NOT NULL DEFAULT 1,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'GHS',
        gateway VARCHAR(20) NOT NULL DEFAULT 'paystack',
        reference VARCHAR(120) NOT NULL,
        accesscode VARCHAR(120) NOT NULL DEFAULT '',
        authorizationurl VARCHAR(255) NOT NULL DEFAULT '',
        gatewaytransactionid VARCHAR(120) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'initialized',
        votescredited TINYINT(1) NOT NULL DEFAULT 0,
        creditedat DATETIME NULL DEFAULT NULL,
        verifiedat DATETIME NULL DEFAULT NULL,
        gatewayresponse VARCHAR(255) NOT NULL DEFAULT '',
        rawresponse LONGTEXT NULL,
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (paymentid),
        UNIQUE KEY uq_votingpayment_reference (reference),
        KEY idx_votingpayment_contest (contestid),
        KEY idx_votingpayment_candidate (candidateid),
        KEY idx_votingpayment_voter (voterid),
        KEY idx_votingpayment_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    voting_ensure_media_directories();
}
}

if(!function_exists('voting_ensure_media_directories')){
function voting_ensure_media_directories(){
    $paths = array(
        __DIR__.DIRECTORY_SEPARATOR.'uploads',
        __DIR__.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'contest-voting',
        __DIR__.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'contest-voting'.DIRECTORY_SEPARATOR.'images',
        __DIR__.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'contest-voting'.DIRECTORY_SEPARATOR.'videos'
    );
    foreach($paths as $path){
        if(!is_dir($path)){
            @mkdir($path, 0777, true);
        }
    }
}
}

if(!function_exists('voting_generate_id')){
function voting_generate_id($prefix){
    return strtoupper(trim((string)$prefix)).date("YmdHis").mt_rand(1000, 999999);
}
}

if(!function_exists('voting_default_branch_id')){
function voting_default_branch_id($con){
    if(isset($_SESSION['BRANCHID']) && trim((string)$_SESSION['BRANCHID']) !== ''){
        return trim((string)$_SESSION['BRANCHID']);
    }
    $res = @mysqli_query($con, "SELECT branchid FROM tblbranch WHERE status='active' ORDER BY datetimeentry ASC LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return trim((string)$row['branchid']);
    }
    $res = @mysqli_query($con, "SELECT branchid FROM tblbranch ORDER BY datetimeentry ASC LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return trim((string)$row['branchid']);
    }
    return '';
}
}

if(!function_exists('voting_is_admin')){
function voting_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'administrator' &&
        in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true);
}
}

if(!function_exists('voting_current_user_can_vote')){
function voting_current_user_can_vote(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        in_array($_SESSION['SYSTEMTYPE'], array('Student', 'Teacher'), true);
}
}

if(!function_exists('voting_current_system_type')){
function voting_current_system_type(){
    return isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
}
}

if(!function_exists('voting_current_user_id')){
function voting_current_user_id(){
    return isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
}
}

if(!function_exists('voting_get_current_voter')){
function voting_get_current_voter($con){
    $userId = voting_current_user_id();
    if($userId === ''){
        return null;
    }
    $userIdEsc = mysqli_real_escape_string($con, $userId);
    $res = @mysqli_query($con, "SELECT userid, firstname, surname, othernames, email, mobile, systemtype, branchid FROM tblsystemuser WHERE userid='$userIdEsc' LIMIT 1");
    if(!$res || !($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return null;
    }
    $name = trim((string)$row['firstname'].' '.(string)$row['othernames'].' '.(string)$row['surname']);
    $row['displayname'] = $name !== '' ? $name : $userId;
    return $row;
}
}

if(!function_exists('voting_candidate_photo')){
function voting_candidate_photo($candidate){
    $path = trim((string)(isset($candidate['photofile']) ? $candidate['photofile'] : ''));
    if($path !== ''){
        return $path;
    }
    return 'images/nexgen-logo.png';
}
}

if(!function_exists('voting_store_media_file')){
function voting_store_media_file($file, $type, &$errorMessage){
    voting_ensure_media_directories();
    $errorMessage = '';
    $type = strtolower(trim((string)$type));
    if(!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE){
        return '';
    }
    if((int)$file['error'] !== UPLOAD_ERR_OK){
        $errorMessage = 'The uploaded '.($type === 'video' ? 'video' : 'image').' could not be processed right now.';
        return false;
    }

    $originalName = isset($file['name']) ? (string)$file['name'] : '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = array();
    $maxBytes = 0;
    $subDir = '';
    if($type === 'video'){
        $allowed = array('mp4','webm','ogg');
        $maxBytes = 25 * 1024 * 1024;
        $subDir = 'videos';
    }else{
        $allowed = array('jpg','jpeg','png','webp','gif');
        $maxBytes = 5 * 1024 * 1024;
        $subDir = 'images';
    }

    if($extension === '' || !in_array($extension, $allowed, true)){
        $errorMessage = $type === 'video'
            ? 'Upload an MP4, WebM, or OGG campaign video.'
            : 'Upload a JPG, PNG, GIF, or WebP image.';
        return false;
    }

    $fileSize = isset($file['size']) ? (int)$file['size'] : 0;
    if($fileSize <= 0 || $fileSize > $maxBytes){
        $errorMessage = $type === 'video'
            ? 'Campaign videos must be 25MB or smaller.'
            : 'Candidate images must be 5MB or smaller.';
        return false;
    }

    $targetName = strtolower($type).'-'.date('YmdHis').'-'.mt_rand(1000, 999999).'.'.$extension;
    $relativePath = 'uploads/contest-voting/'.$subDir.'/'.$targetName;
    $absolutePath = __DIR__.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if(!@move_uploaded_file($file['tmp_name'], $absolutePath)){
        $errorMessage = 'The uploaded file could not be saved right now.';
        return false;
    }
    return $relativePath;
}
}

if(!function_exists('voting_media_absolute_path')){
function voting_media_absolute_path($relativePath){
    $relativePath = trim((string)$relativePath);
    if($relativePath === ''){
        return '';
    }
    $relativePath = str_replace('\\', '/', $relativePath);
    if(strpos($relativePath, 'uploads/contest-voting/') !== 0){
        return '';
    }
    $absolutePath = __DIR__.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return $absolutePath;
}
}

if(!function_exists('voting_delete_media_file')){
function voting_delete_media_file($relativePath){
    $absolutePath = voting_media_absolute_path($relativePath);
    if($absolutePath === ''){
        return false;
    }
    if(!file_exists($absolutePath)){
        return true;
    }
    return @unlink($absolutePath);
}
}

if(!function_exists('voting_contest_accepts_role')){
function voting_contest_accepts_role($contest, $systemType){
    $systemType = strtolower(trim((string)$systemType));
    $eligibility = strtolower(trim((string)(isset($contest['votereligibility']) ? $contest['votereligibility'] : 'both')));
    if($eligibility === 'both'){
        return in_array($systemType, array('student','teacher'), true);
    }
    return $eligibility === $systemType;
}
}

if(!function_exists('voting_resolved_contest_state')){
function voting_resolved_contest_state($contest){
    $status = strtolower(trim((string)(isset($contest['status']) ? $contest['status'] : 'draft')));
    if($status === 'closed' || $status === 'archived' || $status === 'draft'){
        return $status;
    }
    $now = time();
    $startTime = trim((string)(isset($contest['startsat']) ? $contest['startsat'] : ''));
    $endTime = trim((string)(isset($contest['endsat']) ? $contest['endsat'] : ''));
    $startStamp = $startTime !== '' ? strtotime($startTime) : false;
    $endStamp = $endTime !== '' ? strtotime($endTime) : false;
    if($startStamp && $now < $startStamp){
        return 'upcoming';
    }
    if($endStamp && $now > $endStamp){
        return 'closed';
    }
    if($status === 'open'){
        return 'live';
    }
    return $status;
}
}

if(!function_exists('voting_contest_is_open')){
function voting_contest_is_open($contest){
    return voting_resolved_contest_state($contest) === 'live';
}
}

if(!function_exists('voting_status_badge_class')){
function voting_status_badge_class($status){
    $status = strtolower(trim((string)$status));
    if($status === 'live'){
        return 'vote-status vote-status--success';
    }
    if($status === 'upcoming'){
        return 'vote-status vote-status--warning';
    }
    if($status === 'closed' || $status === 'archived'){
        return 'vote-status vote-status--neutral';
    }
    return 'vote-status vote-status--info';
}
}

if(!function_exists('voting_status_label')){
function voting_status_label($status){
    $status = strtolower(trim((string)$status));
    if($status === 'live'){
        return 'Voting Live';
    }
    if($status === 'upcoming'){
        return 'Scheduled';
    }
    if($status === 'closed'){
        return 'Closed';
    }
    if($status === 'archived'){
        return 'Archived';
    }
    return ucfirst($status);
}
}

if(!function_exists('voting_contest_time_label')){
function voting_contest_time_label($contest){
    $status = voting_resolved_contest_state($contest);
    $now = time();
    $startTime = trim((string)(isset($contest['startsat']) ? $contest['startsat'] : ''));
    $endTime = trim((string)(isset($contest['endsat']) ? $contest['endsat'] : ''));
    $startStamp = $startTime !== '' ? strtotime($startTime) : false;
    $endStamp = $endTime !== '' ? strtotime($endTime) : false;
    if($status === 'upcoming' && $startStamp){
        $remaining = $startStamp - $now;
        if($remaining > 0){
            return 'Opens in '.voting_duration_label($remaining);
        }
    }
    if($status === 'live' && $endStamp){
        $remaining = $endStamp - $now;
        if($remaining > 0){
            return 'Closes in '.voting_duration_label($remaining);
        }
    }
    if($status === 'closed' && $endStamp){
        return 'Closed on '.date('d M Y, H:i', $endStamp);
    }
    return '';
}
}

if(!function_exists('voting_duration_label')){
function voting_duration_label($seconds){
    $seconds = max(0, (int)$seconds);
    $days = floor($seconds / 86400);
    $seconds -= $days * 86400;
    $hours = floor($seconds / 3600);
    $seconds -= $hours * 3600;
    $minutes = floor($seconds / 60);
    if($days > 0){
        return $days.'d '.$hours.'h';
    }
    if($hours > 0){
        return $hours.'h '.$minutes.'m';
    }
    return max(1, $minutes).'m';
}
}

if(!function_exists('voting_fetch_contests')){
function voting_fetch_contests($con, $branchId, $systemType = '', $limit = 0){
    ensure_voting_tables($con);
    $branchIdEsc = mysqli_real_escape_string($con, trim((string)$branchId));
    $where = " WHERE c.branchid='$branchIdEsc' ";
    if($systemType !== ''){
        $roleEsc = mysqli_real_escape_string($con, strtolower(trim((string)$systemType)));
        $where .= " AND (LOWER(TRIM(c.votereligibility))='both' OR LOWER(TRIM(c.votereligibility))='$roleEsc') ";
    }
    $limitSql = ($limit > 0) ? " LIMIT ".(int)$limit : "";
    $sql = "SELECT c.*,
            COALESCE(stats.totalvotes, 0) AS totalvotes,
            COALESCE(stats.totalrevenue, 0) AS totalrevenue,
            COALESCE(stats.successpayments, 0) AS successpayments,
            COALESCE(cand.candidatecount, 0) AS candidatecount
        FROM tblvotingcontest c
        LEFT JOIN (
            SELECT contestid,
                SUM(CASE WHEN status='success' AND votescredited=1 THEN votequantity ELSE 0 END) AS totalvotes,
                SUM(CASE WHEN status='success' AND votescredited=1 THEN amount ELSE 0 END) AS totalrevenue,
                COUNT(CASE WHEN status='success' AND votescredited=1 THEN 1 END) AS successpayments
            FROM tblvotingpayment
            GROUP BY contestid
        ) stats ON stats.contestid = c.contestid
        LEFT JOIN (
            SELECT contestid, COUNT(*) AS candidatecount
            FROM tblvotingcandidate
            WHERE status='active'
            GROUP BY contestid
        ) cand ON cand.contestid = c.contestid
        $where
        ORDER BY
            CASE
                WHEN c.status='open' THEN 0
                WHEN c.status='draft' THEN 1
                WHEN c.status='closed' THEN 2
                ELSE 3
            END,
            COALESCE(c.startsat, c.datetimeentry) DESC".$limitSql;
    $res = @mysqli_query($con, $sql);
    $items = array();
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row['resolved_status'] = voting_resolved_contest_state($row);
            $items[] = $row;
        }
    }
    return $items;
}
}

if(!function_exists('voting_fetch_contest_by_id')){
function voting_fetch_contest_by_id($con, $contestId, $branchId = ''){
    ensure_voting_tables($con);
    $contestId = trim((string)$contestId);
    if($contestId === ''){
        return null;
    }
    $contestIdEsc = mysqli_real_escape_string($con, $contestId);
    $where = " WHERE c.contestid='$contestIdEsc' ";
    if(trim((string)$branchId) !== ''){
        $branchIdEsc = mysqli_real_escape_string($con, trim((string)$branchId));
        $where .= " AND c.branchid='$branchIdEsc' ";
    }
    $sql = "SELECT c.*,
            COALESCE(stats.totalvotes, 0) AS totalvotes,
            COALESCE(stats.totalrevenue, 0) AS totalrevenue,
            COALESCE(stats.successpayments, 0) AS successpayments,
            COALESCE(cand.candidatecount, 0) AS candidatecount
        FROM tblvotingcontest c
        LEFT JOIN (
            SELECT contestid,
                SUM(CASE WHEN status='success' AND votescredited=1 THEN votequantity ELSE 0 END) AS totalvotes,
                SUM(CASE WHEN status='success' AND votescredited=1 THEN amount ELSE 0 END) AS totalrevenue,
                COUNT(CASE WHEN status='success' AND votescredited=1 THEN 1 END) AS successpayments
            FROM tblvotingpayment
            GROUP BY contestid
        ) stats ON stats.contestid = c.contestid
        LEFT JOIN (
            SELECT contestid, COUNT(*) AS candidatecount
            FROM tblvotingcandidate
            WHERE status='active'
            GROUP BY contestid
        ) cand ON cand.contestid = c.contestid
        $where
        LIMIT 1";
    $res = @mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $row['resolved_status'] = voting_resolved_contest_state($row);
        return $row;
    }
    return null;
}
}

if(!function_exists('voting_fetch_candidates')){
function voting_fetch_candidates($con, $contestId, $includeInactive = false){
    ensure_voting_tables($con);
    $contestId = trim((string)$contestId);
    if($contestId === ''){
        return array();
    }
    $contestIdEsc = mysqli_real_escape_string($con, $contestId);
    $where = " WHERE c.contestid='$contestIdEsc' ";
    if(!$includeInactive){
        $where .= " AND c.status='active' ";
    }
    $sql = "SELECT c.*,
            COALESCE(ps.totalvotes, 0) AS totalvotes,
            COALESCE(ps.totalamount, 0) AS totalamount,
            COALESCE(ps.transactioncount, 0) AS transactioncount
        FROM tblvotingcandidate c
        LEFT JOIN (
            SELECT contestid, candidateid,
                SUM(votequantity) AS totalvotes,
                SUM(amount) AS totalamount,
                COUNT(*) AS transactioncount
            FROM tblvotingpayment
            WHERE status='success' AND votescredited=1
            GROUP BY contestid, candidateid
        ) ps ON ps.contestid = c.contestid AND ps.candidateid = c.candidateid
        $where
        ORDER BY COALESCE(ps.totalvotes, 0) DESC, c.displayorder ASC, c.candidatename ASC";
    $res = @mysqli_query($con, $sql);
    $items = array();
    $rank = 0;
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $rank++;
            $row['rankposition'] = $rank;
            $items[] = $row;
        }
    }
    return $items;
}
}

if(!function_exists('voting_fetch_candidate_by_id')){
function voting_fetch_candidate_by_id($con, $candidateId, $contestId = '', $includeInactive = true){
    $candidateId = trim((string)$candidateId);
    if($candidateId === ''){
        return null;
    }
    $items = array();
    if(trim((string)$contestId) !== ''){
        $items = voting_fetch_candidates($con, $contestId, $includeInactive);
    }else{
        ensure_voting_tables($con);
        $candidateIdEsc = mysqli_real_escape_string($con, $candidateId);
        $where = " WHERE c.candidateid='$candidateIdEsc' ";
        if(!$includeInactive){
            $where .= " AND c.status='active' ";
        }
        $sql = "SELECT c.*,
                COALESCE(ps.totalvotes, 0) AS totalvotes,
                COALESCE(ps.totalamount, 0) AS totalamount,
                COALESCE(ps.transactioncount, 0) AS transactioncount
            FROM tblvotingcandidate c
            LEFT JOIN (
                SELECT contestid, candidateid,
                    SUM(votequantity) AS totalvotes,
                    SUM(amount) AS totalamount,
                    COUNT(*) AS transactioncount
                FROM tblvotingpayment
                WHERE status='success' AND votescredited=1
                GROUP BY contestid, candidateid
            ) ps ON ps.contestid = c.contestid AND ps.candidateid = c.candidateid
            $where
            LIMIT 1";
        $res = @mysqli_query($con, $sql);
        if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
            return $row;
        }
        return null;
    }
    foreach($items as $item){
        if((string)$item['candidateid'] === $candidateId){
            return $item;
        }
    }
    return null;
}
}

if(!function_exists('voting_campaign_history_log')){
function voting_campaign_history_log($con, $contestId, $candidateId, $candidateName, $actionType, $actionTitle, $actionDetails = '', $recordedBy = ''){
    ensure_voting_tables($con);
    $contestId = trim((string)$contestId);
    $candidateId = trim((string)$candidateId);
    $actionType = trim((string)$actionType);
    $actionTitle = trim((string)$actionTitle);
    if($contestId === '' || $candidateId === '' || $actionType === '' || $actionTitle === ''){
        return false;
    }
    $recordedBy = trim((string)$recordedBy);
    if($recordedBy === ''){
        $recordedBy = voting_current_user_id();
    }

    $contestIdEsc = mysqli_real_escape_string($con, $contestId);
    $candidateIdEsc = mysqli_real_escape_string($con, $candidateId);
    $candidateNameEsc = mysqli_real_escape_string($con, trim((string)$candidateName));
    $actionTypeEsc = mysqli_real_escape_string($con, $actionType);
    $actionTitleEsc = mysqli_real_escape_string($con, $actionTitle);
    $actionDetailsEsc = mysqli_real_escape_string($con, trim((string)$actionDetails));
    $recordedByEsc = mysqli_real_escape_string($con, $recordedBy);

    return @mysqli_query($con, "INSERT INTO tblvotingcampaignhistory(
        contestid, candidateid, candidatename, actiontype, actiontitle, actiondetails, recordedby, datetimeentry
    ) VALUES (
        '$contestIdEsc', '$candidateIdEsc', '$candidateNameEsc', '$actionTypeEsc', '$actionTitleEsc', '$actionDetailsEsc', '$recordedByEsc', NOW()
    )");
}
}

if(!function_exists('voting_fetch_campaign_history')){
function voting_fetch_campaign_history($con, $contestId, $candidateId = '', $limit = 40){
    ensure_voting_tables($con);
    $contestId = trim((string)$contestId);
    if($contestId === ''){
        return array();
    }
    $candidateId = trim((string)$candidateId);
    $limit = max(1, min(100, (int)$limit));
    $contestIdEsc = mysqli_real_escape_string($con, $contestId);
    $where = " WHERE h.contestid='$contestIdEsc' ";
    if($candidateId !== ''){
        $candidateIdEsc = mysqli_real_escape_string($con, $candidateId);
        $where .= " AND h.candidateid='$candidateIdEsc' ";
    }
    $sql = "SELECT h.*,
            COALESCE(NULLIF(TRIM(h.candidatename), ''), c.candidatename) AS display_candidate_name,
            su.firstname AS actor_firstname,
            su.othernames AS actor_othernames,
            su.surname AS actor_surname
        FROM tblvotingcampaignhistory h
        LEFT JOIN tblvotingcandidate c ON c.candidateid=h.candidateid
        LEFT JOIN tblsystemuser su ON su.userid=h.recordedby
        $where
        ORDER BY h.datetimeentry DESC, h.historyid DESC
        LIMIT $limit";
    $res = @mysqli_query($con, $sql);
    $items = array();
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $actorName = trim((string)$row['actor_firstname'].' '.(string)$row['actor_othernames'].' '.(string)$row['actor_surname']);
            $row['actorname'] = $actorName !== '' ? $actorName : trim((string)$row['recordedby']);
            $items[] = $row;
        }
    }
    return $items;
}
}

if(!function_exists('voting_featured_contest')){
function voting_featured_contest($con, $branchId, $systemType){
    $items = voting_fetch_contests($con, $branchId, $systemType);
    if(empty($items)){
        return null;
    }
    $live = array();
    $upcoming = array();
    $other = array();
    foreach($items as $item){
        $state = isset($item['resolved_status']) ? $item['resolved_status'] : voting_resolved_contest_state($item);
        if($state === 'live'){
            $live[] = $item;
        }elseif($state === 'upcoming'){
            $upcoming[] = $item;
        }else{
            $other[] = $item;
        }
    }
    if(!empty($live)){
        return $live[0];
    }
    if(!empty($upcoming)){
        return $upcoming[0];
    }
    return $other[0];
}
}

if(!function_exists('voting_leaderboard_summary')){
function voting_leaderboard_summary($contest, $candidates){
    $totalVotes = 0;
    $totalRevenue = 0;
    $leaderName = 'No contestants yet';
    $leaderVotes = 0;
    foreach((array)$candidates as $candidate){
        $votes = (int)(isset($candidate['totalvotes']) ? $candidate['totalvotes'] : 0);
        $amount = (float)(isset($candidate['totalamount']) ? $candidate['totalamount'] : 0);
        $totalVotes += $votes;
        $totalRevenue += $amount;
        if($votes > $leaderVotes){
            $leaderVotes = $votes;
            $leaderName = trim((string)$candidate['candidatename']);
        }
    }
    return array(
        'total_votes' => $totalVotes,
        'total_revenue' => $totalRevenue,
        'leader_name' => $leaderName,
        'leader_votes' => $leaderVotes,
        'candidate_count' => count((array)$candidates),
        'price_per_vote' => isset($contest['pricepervote']) ? (float)$contest['pricepervote'] : 0
    );
}
}

if(!function_exists('voting_vote_quantity_bounds')){
function voting_vote_quantity_bounds($contest){
    $minVotes = max(1, (int)(isset($contest['minvotes']) ? $contest['minvotes'] : 1));
    $maxVotes = max($minVotes, (int)(isset($contest['maxvotes']) ? $contest['maxvotes'] : 100));
    return array($minVotes, $maxVotes);
}
}

if(!function_exists('voting_create_payment_record')){
function voting_create_payment_record($con, $data){
    ensure_voting_tables($con);
    $columns = array(
        'contestid','candidateid','branchid','voterid','votersystemtype','votername','voteremail','votermobile',
        'votequantity','amount','currency','gateway','reference','accesscode','authorizationurl','gatewaytransactionid',
        'status','votescredited','creditedat','verifiedat','gatewayresponse','rawresponse'
    );
    $values = array();
    foreach($columns as $column){
        $values[$column] = isset($data[$column]) ? $data[$column] : '';
    }
    $stmt = @mysqli_prepare($con, "INSERT INTO tblvotingpayment(
        contestid,candidateid,branchid,voterid,votersystemtype,votername,voteremail,votermobile,
        votequantity,amount,currency,gateway,reference,accesscode,authorizationurl,gatewaytransactionid,
        status,votescredited,creditedat,verifiedat,gatewayresponse,rawresponse,datetimeentry,updatedat
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    if(!$stmt){
        return false;
    }
    $types = "ssssssssidsssssssissss";
    @mysqli_stmt_bind_param(
        $stmt,
        $types,
        $values['contestid'], $values['candidateid'], $values['branchid'], $values['voterid'], $values['votersystemtype'], $values['votername'], $values['voteremail'], $values['votermobile'],
        $values['votequantity'], $values['amount'], $values['currency'], $values['gateway'], $values['reference'], $values['accesscode'], $values['authorizationurl'], $values['gatewaytransactionid'],
        $values['status'], $values['votescredited'], $values['creditedat'], $values['verifiedat'], $values['gatewayresponse'], $values['rawresponse']
    );
    $ok = @mysqli_stmt_execute($stmt);
    @mysqli_stmt_close($stmt);
    if(!$ok){
        return false;
    }
    return @mysqli_insert_id($con);
}
}

if(!function_exists('voting_get_payment_by_reference')){
function voting_get_payment_by_reference($con, $reference){
    ensure_voting_tables($con);
    $reference = trim((string)$reference);
    if($reference === ''){
        return null;
    }
    $referenceEsc = mysqli_real_escape_string($con, $reference);
    $res = @mysqli_query($con, "SELECT * FROM tblvotingpayment WHERE reference='$referenceEsc' LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('voting_update_payment_record')){
function voting_update_payment_record($con, $paymentId, $fields){
    ensure_voting_tables($con);
    $paymentId = (int)$paymentId;
    if($paymentId <= 0 || !is_array($fields) || empty($fields)){
        return false;
    }
    $sets = array();
    foreach($fields as $column => $value){
        $column = trim((string)$column);
        if($column === ''){
            continue;
        }
        if($value === null){
            $sets[] = "`$column`=NULL";
        }else{
            $valueEsc = mysqli_real_escape_string($con, (string)$value);
            $sets[] = "`$column`='$valueEsc'";
        }
    }
    if(empty($sets)){
        return false;
    }
    $sets[] = "updatedat=NOW()";
    return @mysqli_query($con, "UPDATE tblvotingpayment SET ".implode(', ', $sets)." WHERE paymentid=".$paymentId." LIMIT 1");
}
}

if(!function_exists('voting_payment_reference')){
function voting_payment_reference(){
    return 'VOTE-'.date('YmdHis').'-'.mt_rand(100000, 999999);
}
}

if(!function_exists('voting_payment_email')){
function voting_payment_email($voter){
    $email = trim((string)(isset($voter['email']) ? $voter['email'] : ''));
    if($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)){
        return $email;
    }
    return 'ayirebishs@ges.gov.gh';
}
}

if(!function_exists('voting_process_paystack_payment_result')){
function voting_process_paystack_payment_result($con, $payment, $gatewayData, $rawResponse, $gatewayMessage){
    ensure_voting_tables($con);
    $result = array(
        'payment' => $payment,
        'contest' => voting_fetch_contest_by_id($con, isset($payment['contestid']) ? $payment['contestid'] : ''),
        'candidate' => voting_fetch_candidates($con, isset($payment['contestid']) ? $payment['contestid'] : '', true),
        'stored_status' => isset($payment['status']) ? $payment['status'] : '',
        'integrity_failed' => false
    );

    $gatewayStatus = strtolower(trim((string)(isset($gatewayData['status']) ? $gatewayData['status'] : '')));
    $amountMinor = isset($gatewayData['amount']) ? (int)$gatewayData['amount'] : 0;
    $currency = strtoupper(trim((string)(isset($gatewayData['currency']) ? $gatewayData['currency'] : '')));
    $expectedMinor = 0;
    if(function_exists('online_admission_money_minor_units')){
        $expectedMinor = (int)online_admission_money_minor_units(isset($payment['amount']) ? $payment['amount'] : 0);
    }else{
        $expectedMinor = (int)round(((float)(isset($payment['amount']) ? $payment['amount'] : 0)) * 100);
    }
    if($amountMinor > 0 && $expectedMinor > 0 && $amountMinor !== $expectedMinor){
        $result['integrity_failed'] = true;
    }
    if($currency !== '' && strtoupper(trim((string)(isset($payment['currency']) ? $payment['currency'] : ''))) !== '' &&
        $currency !== strtoupper(trim((string)$payment['currency']))){
        $result['integrity_failed'] = true;
    }

    $mappedStatus = 'pending';
    if($gatewayStatus === 'success'){
        $mappedStatus = 'success';
    }elseif(in_array($gatewayStatus, array('failed', 'reversed'), true)){
        $mappedStatus = 'failed';
    }elseif(in_array($gatewayStatus, array('abandoned', 'cancelled'), true)){
        $mappedStatus = 'abandoned';
    }

    $update = array(
        'gatewaytransactionid' => isset($gatewayData['id']) ? (string)$gatewayData['id'] : '',
        'status' => $mappedStatus,
        'gatewayresponse' => $gatewayMessage !== '' ? $gatewayMessage : (isset($gatewayData['gateway_response']) ? (string)$gatewayData['gateway_response'] : ucfirst($mappedStatus)),
        'rawresponse' => (string)$rawResponse,
        'verifiedat' => date('Y-m-d H:i:s')
    );

    $alreadyCredited = isset($payment['votescredited']) && (int)$payment['votescredited'] === 1;
    if($mappedStatus === 'success' && !$result['integrity_failed'] && !$alreadyCredited){
        $update['votescredited'] = 1;
        $update['creditedat'] = date('Y-m-d H:i:s');
    }
    voting_update_payment_record($con, isset($payment['paymentid']) ? $payment['paymentid'] : 0, $update);
    $stored = voting_get_payment_by_reference($con, isset($payment['reference']) ? $payment['reference'] : '');
    if($stored){
        $result['payment'] = $stored;
        $result['stored_status'] = isset($stored['status']) ? $stored['status'] : $mappedStatus;
    }else{
        $result['stored_status'] = $mappedStatus;
    }
    $result['contest'] = voting_fetch_contest_by_id($con, isset($payment['contestid']) ? $payment['contestid'] : '');
    $candidateList = voting_fetch_candidates($con, isset($payment['contestid']) ? $payment['contestid'] : '', true);
    $result['candidate'] = null;
    foreach($candidateList as $candidateRow){
        if((string)$candidateRow['candidateid'] === (string)(isset($payment['candidateid']) ? $payment['candidateid'] : '')){
            $result['candidate'] = $candidateRow;
            break;
        }
    }
    return $result;
}
}

if(!function_exists('voting_dashboard_snapshot')){
function voting_dashboard_snapshot($con, $branchId, $systemType){
    $contest = voting_featured_contest($con, $branchId, $systemType);
    if(!$contest){
        return null;
    }
    $candidates = voting_fetch_candidates($con, $contest['contestid']);
    $summary = voting_leaderboard_summary($contest, $candidates);
    return array(
        'contest' => $contest,
        'candidates' => $candidates,
        'summary' => $summary,
        'top_candidates' => array_slice($candidates, 0, 3)
    );
}
}
