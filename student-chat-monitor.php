<?php
session_start();
$_SESSION['Message'] = isset($_SESSION['Message']) ? $_SESSION['Message'] : '';
include("check-login.php");
include_once("student-chat-utils.php");
student_chat_ensure_tables($con);

if(!student_chat_is_admin()){
    header("location:".student_chat_landing_page());
    exit();
}

if(!function_exists('scm_esc')){
function scm_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if(!function_exists('scm_alert')){
function scm_alert($type, $message){
    $type = trim((string)$type);
    if(!in_array($type, array('success','error','warning','info'), true)){
        $type = 'info';
    }
    $icon = 'fa-info-circle';
    if($type === 'success'){ $icon = 'fa-check-circle'; }
    elseif($type === 'error'){ $icon = 'fa-exclamation-circle'; }
    elseif($type === 'warning'){ $icon = 'fa-exclamation-triangle'; }
    return "<div class='student-chat-alert student-chat-alert--".$type."'><i class='fa ".$icon."'></i><span>".scm_esc($message)."</span></div>";
}
}

if(!function_exists('scm_redirect')){
function scm_redirect($conversationId = ''){
    $params = array();
    foreach(array('q','status','page') as $key){
        if(isset($_GET[$key]) && trim((string)$_GET[$key]) !== ''){
            $params[$key] = trim((string)$_GET[$key]);
        }
    }
    if(trim((string)$conversationId) !== ''){
        $params['conversation'] = trim((string)$conversationId);
    }
    $query = http_build_query($params);
    header("location:student-chat-monitor.php".($query !== '' ? '?'.$query : ''));
    exit();
}
}

if(!function_exists('scm_time')){
function scm_time($value){
    $value = trim((string)$value);
    if($value === ''){
        return 'Not recorded';
    }
    $time = strtotime($value);
    return $time ? date('d M Y, H:i', $time) : $value;
}
}

if(!function_exists('scm_status_class')){
function scm_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === 'accepted'){ return 'student-chat-pill student-chat-pill--success'; }
    if($status === 'pending'){ return 'student-chat-pill student-chat-pill--warning'; }
    if($status === 'blocked'){ return 'student-chat-pill student-chat-pill--danger'; }
    if($status === 'declined' || $status === 'cancelled'){ return 'student-chat-pill student-chat-pill--neutral'; }
    return 'student-chat-pill student-chat-pill--neutral';
}
}

if(!function_exists('scm_student_name')){
function scm_student_name($row, $prefix){
    $parts = array();
    foreach(array('firstname','othernames','surname') as $field){
        $value = isset($row[$prefix.$field]) ? trim((string)$row[$prefix.$field]) : '';
        if($value !== ''){
            $parts[] = $value;
        }
    }
    $name = trim(implode(' ', $parts));
    if($name !== ''){
        return $name;
    }
    $base = rtrim((string)$prefix, '_');
    foreach(array($prefix.'userid', $base.'userid', $base.'id', 'userid', 'senderid', 'adminid') as $fallbackKey){
        if(isset($row[$fallbackKey]) && trim((string)$row[$fallbackKey]) !== ''){
            return trim((string)$row[$fallbackKey]);
        }
    }
    return 'Student';
}
}

if(!function_exists('scm_student_meta')){
function scm_student_meta($con, $studentId){
    $profile = student_chat_student_profile($con, $studentId);
    if(!$profile){
        return array(
            'userid' => trim((string)$studentId),
            'display_name' => trim((string)$studentId),
            'schoolindexnumber' => '',
            'photo_src' => 'uploads/comm.gif',
            'class_name' => '',
            'housename' => ''
        );
    }
    return $profile;
}
}

$adminId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';

if(isset($_POST['mark_report_reviewed'])){
    $reportId = isset($_POST['reportid']) ? trim((string)$_POST['reportid']) : '';
    $conversationId = isset($_POST['conversationid']) ? trim((string)$_POST['conversationid']) : '';
    $reportEsc = mysqli_real_escape_string($con, $reportId);
    $adminEsc = mysqli_real_escape_string($con, $adminId);
    $updated = false;
    if($reportEsc !== ''){
        $updated = mysqli_query($con, "UPDATE tblstudentchatreport
            SET status='reviewed', reviewedby='$adminEsc', reviewedat=NOW()
            WHERE reportid='$reportEsc' AND status='open'
            LIMIT 1");
    }
    if($updated && mysqli_affected_rows($con) > 0){
        student_chat_admin_log_action($con, $conversationId, $adminId, 'review_report', 'Reviewed report '.$reportId);
        $_SESSION['Message'] = scm_alert('success', 'Report marked as reviewed.');
    }else{
        $_SESSION['Message'] = scm_alert('warning', 'The report could not be updated, or it was already reviewed.');
    }
    scm_redirect($conversationId);
}

if(isset($_POST['mark_conversation_reports_reviewed'])){
    $conversationId = isset($_POST['conversationid']) ? trim((string)$_POST['conversationid']) : '';
    $conversationEsc = mysqli_real_escape_string($con, $conversationId);
    $adminEsc = mysqli_real_escape_string($con, $adminId);
    $updated = false;
    if($conversationEsc !== ''){
        $updated = mysqli_query($con, "UPDATE tblstudentchatreport
            SET status='reviewed', reviewedby='$adminEsc', reviewedat=NOW()
            WHERE conversationid='$conversationEsc' AND status='open'");
    }
    if($updated){
        student_chat_admin_log_action($con, $conversationId, $adminId, 'review_reports', 'Reviewed open reports for conversation');
        $_SESSION['Message'] = scm_alert('success', 'Open reports for this conversation have been marked as reviewed.');
    }else{
        $_SESSION['Message'] = scm_alert('warning', 'The reports could not be updated.');
    }
    scm_redirect($conversationId);
}

if(isset($_POST['block_conversation'])){
    $conversationId = isset($_POST['conversationid']) ? trim((string)$_POST['conversationid']) : '';
    $reason = isset($_POST['block_reason']) ? trim((string)$_POST['block_reason']) : '';
    if($reason === ''){
        $reason = 'Blocked by school administrator after chat review.';
    }
    if(strlen($reason) > 180){
        $reason = substr($reason, 0, 180);
    }
    $conversationEsc = mysqli_real_escape_string($con, $conversationId);
    $adminEsc = mysqli_real_escape_string($con, $adminId);
    $reasonEsc = mysqli_real_escape_string($con, $reason);
    $updated = false;
    if($conversationEsc !== ''){
        $updated = mysqli_query($con, "UPDATE tblstudentchatconversation
            SET status='blocked', blockedby='$adminEsc', blockreason='$reasonEsc', updatedat=NOW()
            WHERE conversationid='$conversationEsc'
            LIMIT 1");
    }
    if($updated && mysqli_affected_rows($con) > 0){
        student_chat_admin_log_action($con, $conversationId, $adminId, 'block_conversation', $reason);
        $_SESSION['Message'] = scm_alert('success', 'Conversation blocked. Students can no longer send messages in this chat.');
    }else{
        $_SESSION['Message'] = scm_alert('warning', 'The conversation could not be blocked.');
    }
    scm_redirect($conversationId);
}

$flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : '';
$_SESSION['Message'] = '';

$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : 'reported';
$allowedStatuses = array('reported','accepted','pending','blocked','declined','cancelled','all');
if(!in_array($statusFilter, $allowedStatuses, true)){
    $statusFilter = 'reported';
}
$searchQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1){
    $page = 1;
}
$perPage = 18;
$offset = ($page - 1) * $perPage;

$stats = array('open_reports' => 0, 'active' => 0, 'pending' => 0, 'blocked' => 0, 'messages' => 0);
$statRes = mysqli_query($con, "SELECT
        SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) AS active_total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_total,
        SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) AS blocked_total
    FROM tblstudentchatconversation");
if($statRes && ($statRow = mysqli_fetch_array($statRes, MYSQLI_ASSOC))){
    $stats['active'] = (int)$statRow['active_total'];
    $stats['pending'] = (int)$statRow['pending_total'];
    $stats['blocked'] = (int)$statRow['blocked_total'];
}
$openReportRes = mysqli_query($con, "SELECT COUNT(*) AS total_open FROM tblstudentchatreport WHERE status='open'");
if($openReportRes && ($statRow = mysqli_fetch_array($openReportRes, MYSQLI_ASSOC))){
    $stats['open_reports'] = (int)$statRow['total_open'];
}
$messageStatRes = mysqli_query($con, "SELECT COUNT(*) AS total_messages FROM tblstudentchatmessage WHERE status<>'deleted'");
if($messageStatRes && ($statRow = mysqli_fetch_array($messageStatRes, MYSQLI_ASSOC))){
    $stats['messages'] = (int)$statRow['total_messages'];
}

$where = array("1=1");
if($statusFilter === 'reported'){
    $where[] = "EXISTS (
        SELECT 1 FROM tblstudentchatreport rr
        WHERE rr.conversationid=c.conversationid AND rr.status='open'
    )";
}elseif($statusFilter !== 'all'){
    $statusEsc = mysqli_real_escape_string($con, $statusFilter);
    $where[] = "c.status='$statusEsc'";
}
if($searchQuery !== ''){
    $like = mysqli_real_escape_string($con, '%'.$searchQuery.'%');
    $where[] = "(
        c.conversationid LIKE '$like'
        OR c.requesterid LIKE '$like'
        OR c.recipientid LIKE '$like'
        OR rq.schoolindexnumber LIKE '$like'
        OR rc.schoolindexnumber LIKE '$like'
        OR rq.firstname LIKE '$like'
        OR rq.othernames LIKE '$like'
        OR rq.surname LIKE '$like'
        OR rc.firstname LIKE '$like'
        OR rc.othernames LIKE '$like'
        OR rc.surname LIKE '$like'
    )";
}
$whereSql = implode(' AND ', $where);

$totalRows = 0;
$totalRes = mysqli_query($con, "SELECT COUNT(*) AS total_rows
    FROM tblstudentchatconversation c
    LEFT JOIN tblsystemuser rq ON rq.userid=c.requesterid
    LEFT JOIN tblsystemuser rc ON rc.userid=c.recipientid
    WHERE $whereSql");
if($totalRes && ($totalRow = mysqli_fetch_array($totalRes, MYSQLI_ASSOC))){
    $totalRows = (int)$totalRow['total_rows'];
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if($page > $totalPages){
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$conversationRows = array();
$listRes = mysqli_query($con, "SELECT c.*,
        rq.userid AS rq_userid, rq.firstname AS rq_firstname, rq.othernames AS rq_othernames, rq.surname AS rq_surname, rq.schoolindexnumber AS rq_schoolindexnumber,
        rc.userid AS rc_userid, rc.firstname AS rc_firstname, rc.othernames AS rc_othernames, rc.surname AS rc_surname, rc.schoolindexnumber AS rc_schoolindexnumber,
        (SELECT COUNT(*) FROM tblstudentchatmessage m WHERE m.conversationid=c.conversationid AND m.status<>'deleted') AS message_total,
        (SELECT COUNT(*) FROM tblstudentchatreport r WHERE r.conversationid=c.conversationid AND r.status='open') AS open_reports,
        (SELECT m.messagetext FROM tblstudentchatmessage m WHERE m.conversationid=c.conversationid AND m.status<>'deleted' ORDER BY m.datetimeentry DESC LIMIT 1) AS last_message,
        (SELECT m.datetimeentry FROM tblstudentchatmessage m WHERE m.conversationid=c.conversationid AND m.status<>'deleted' ORDER BY m.datetimeentry DESC LIMIT 1) AS last_message_at
    FROM tblstudentchatconversation c
    LEFT JOIN tblsystemuser rq ON rq.userid=c.requesterid
    LEFT JOIN tblsystemuser rc ON rc.userid=c.recipientid
    WHERE $whereSql
    ORDER BY open_reports DESC, COALESCE(c.lastmessageat, c.updatedat, c.requestedat) DESC
    LIMIT $offset, $perPage");
if($listRes){
    while($row = mysqli_fetch_array($listRes, MYSQLI_ASSOC)){
        $conversationRows[] = $row;
    }
}

$activeConversationId = isset($_GET['conversation']) ? trim((string)$_GET['conversation']) : '';
$activeConversation = null;
$activeRequester = null;
$activeRecipient = null;
$activeMessages = array();
$activeReports = array();
$auditRows = array();
if($activeConversationId !== ''){
    $activeEsc = mysqli_real_escape_string($con, $activeConversationId);
    $activeRes = mysqli_query($con, "SELECT * FROM tblstudentchatconversation WHERE conversationid='$activeEsc' LIMIT 1");
    if($activeRes && ($row = mysqli_fetch_array($activeRes, MYSQLI_ASSOC))){
        $activeConversation = $row;
        $activeRequester = scm_student_meta($con, $row['requesterid']);
        $activeRecipient = scm_student_meta($con, $row['recipientid']);

        $viewKey = '__student_chat_admin_monitor_view_'.$activeConversationId;
        $lastView = isset($_SESSION[$viewKey]) ? (int)$_SESSION[$viewKey] : 0;
        if($lastView === 0 || (time() - $lastView) > 300){
            student_chat_admin_log_action($con, $activeConversationId, $adminId, 'view', 'Opened conversation monitor');
            $_SESSION[$viewKey] = time();
        }

        $msgRes = mysqli_query($con, "SELECT m.*,su.firstname,su.othernames,su.surname, su.schoolindexnumber
            FROM tblstudentchatmessage m
            LEFT JOIN tblsystemuser su ON su.userid=m.senderid
            WHERE m.conversationid='$activeEsc' AND m.status<>'deleted'
            ORDER BY m.datetimeentry ASC
            LIMIT 250");
        if($msgRes){
            while($msgRow = mysqli_fetch_array($msgRes, MYSQLI_ASSOC)){
                $msgRow['sender_name'] = student_chat_full_name($msgRow);
                $activeMessages[] = $msgRow;
            }
        }

        $reportRowsRes = mysqli_query($con, "SELECT r.*,m.messagetext,
                rp.firstname AS reporter_firstname, rp.othernames AS reporter_othernames, rp.surname AS reporter_surname,
                ru.firstname AS reported_firstname, ru.othernames AS reported_othernames, ru.surname AS reported_surname
            FROM tblstudentchatreport r
            LEFT JOIN tblstudentchatmessage m ON m.messageid=r.messageid
            LEFT JOIN tblsystemuser rp ON rp.userid=r.reporterid
            LEFT JOIN tblsystemuser ru ON ru.userid=r.reporteduserid
            WHERE r.conversationid='$activeEsc'
            ORDER BY CASE WHEN r.status='open' THEN 0 ELSE 1 END, r.datetimeentry DESC");
        if($reportRowsRes){
            while($reportRow = mysqli_fetch_array($reportRowsRes, MYSQLI_ASSOC)){
                $activeReports[] = $reportRow;
            }
        }

        $auditRes = mysqli_query($con, "SELECT av.*,su.firstname,su.othernames,su.surname
            FROM tblstudentchatadminview av
            LEFT JOIN tblsystemuser su ON su.userid=av.adminid
            WHERE av.conversationid='$activeEsc'
            ORDER BY av.datetimeentry DESC
            LIMIT 8");
        if($auditRes){
            while($auditRow = mysqli_fetch_array($auditRes, MYSQLI_ASSOC)){
                $auditRow['admin_name'] = scm_student_name($auditRow, '');
                $auditRows[] = $auditRow;
            }
        }
    }
}

function scm_page_url($pageNumber){
    $params = $_GET;
    if($pageNumber <= 1){
        unset($params['page']);
    }else{
        $params['page'] = $pageNumber;
    }
    unset($params['conversation']);
    $query = http_build_query($params);
    return 'student-chat-monitor.php'.($query !== '' ? '?'.$query : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/student-chat.css">
</head>
<body class="body-style student-chat-page student-chat-monitor-page">
    <div class="header"><?php include("menu.php"); ?></div>

    <main class="student-chat-shell">
        <?php if($flashMessage !== ""){ ?><div class="student-chat-flash"><?php echo $flashMessage; ?></div><?php } ?>

        <section class="student-chat-hero student-chat-hero--settings">
            <div>
                <span class="student-chat-kicker"><i class="fa fa-eye"></i> Student Chat Monitor</span>
                <h1>Review student private chats responsibly.</h1>
                <p>Use this workspace for reported conversations, safety checks, and official moderation actions. Conversation access is recorded for accountability.</p>
            </div>
            <aside>
                <article><span>Open Reports</span><strong><?php echo (int)$stats['open_reports']; ?></strong></article>
                <article><span>Messages Stored</span><strong><?php echo (int)$stats['messages']; ?></strong></article>
            </aside>
        </section>

        <section class="student-chat-rules student-chat-rules--monitor">
            <span><i class="fa fa-shield"></i> Review chats only for safety or discipline.</span>
            <span><i class="fa fa-history"></i> Admin views and actions are logged.</span>
            <span><i class="fa fa-flag"></i> Reported conversations appear first.</span>
        </section>

        <section class="student-chat-monitor-stats">
            <article><span>Active Chats</span><strong><?php echo (int)$stats['active']; ?></strong></article>
            <article><span>Pending Requests</span><strong><?php echo (int)$stats['pending']; ?></strong></article>
            <article><span>Blocked Conversations</span><strong><?php echo (int)$stats['blocked']; ?></strong></article>
            <article><span>Open Reports</span><strong><?php echo (int)$stats['open_reports']; ?></strong></article>
        </section>

        <div class="student-chat-monitor-layout">
            <aside class="student-chat-card student-chat-monitor-list">
                <div class="student-chat-card__head">
                    <div>
                        <span>Moderation Queue</span>
                        <h2>Conversations</h2>
                    </div>
                    <a class="student-chat-btn student-chat-btn--soft" href="student-chat-settings.php"><i class="fa fa-sliders"></i> Chat Control</a>
                </div>

                <form method="get" class="student-chat-monitor-filter">
                    <label for="q">Search student or conversation</label>
                    <input type="search" id="q" name="q" value="<?php echo scm_esc($searchQuery); ?>" placeholder="Name, ID, index number">
                    <label for="status">Show</label>
                    <select id="status" name="status">
                        <option value="reported"<?php echo $statusFilter === 'reported' ? ' selected' : ''; ?>>Open reports first</option>
                        <option value="accepted"<?php echo $statusFilter === 'accepted' ? ' selected' : ''; ?>>Active chats</option>
                        <option value="pending"<?php echo $statusFilter === 'pending' ? ' selected' : ''; ?>>Pending requests</option>
                        <option value="blocked"<?php echo $statusFilter === 'blocked' ? ' selected' : ''; ?>>Blocked conversations</option>
                        <option value="declined"<?php echo $statusFilter === 'declined' ? ' selected' : ''; ?>>Declined requests</option>
                        <option value="cancelled"<?php echo $statusFilter === 'cancelled' ? ' selected' : ''; ?>>Cancelled requests</option>
                        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>All conversations</option>
                    </select>
                    <div class="student-chat-monitor-filter__actions">
                        <button type="submit" class="student-chat-btn student-chat-btn--primary"><i class="fa fa-search"></i> Search</button>
                        <a class="student-chat-btn student-chat-btn--soft" href="student-chat-monitor.php"><i class="fa fa-times"></i> Clear</a>
                    </div>
                </form>

                <div class="student-chat-monitor-results">
                    <?php if(empty($conversationRows)){ ?>
                    <div class="student-chat-empty">
                        <h3>No conversations found</h3>
                        <p>Try another filter, or search by student name, user ID, or index number.</p>
                    </div>
                    <?php }else{ ?>
                        <?php foreach($conversationRows as $row){
                            $isSelected = $activeConversation && (string)$activeConversation['conversationid'] === (string)$row['conversationid'];
                            $rowParams = $_GET;
                            $rowParams['conversation'] = $row['conversationid'];
                            $rowUrl = 'student-chat-monitor.php?'.http_build_query($rowParams);
                        ?>
                        <a class="student-chat-monitor-item<?php echo $isSelected ? ' is-active' : ''; ?>" href="<?php echo scm_esc($rowUrl); ?>">
                            <div>
                                <strong><?php echo scm_esc(scm_student_name($row, 'rq_')); ?></strong>
                                <span>with <?php echo scm_esc(scm_student_name($row, 'rc_')); ?></span>
                            </div>
                            <small><?php echo scm_esc(student_chat_status_label($row['status'])); ?> · <?php echo number_format((int)$row['message_total']); ?> message(s)</small>
                            <?php if((int)$row['open_reports'] > 0){ ?><em><i class="fa fa-flag"></i> <?php echo (int)$row['open_reports']; ?> open report(s)</em><?php } ?>
                            <?php if(trim((string)$row['last_message']) !== ''){ ?><p><?php echo scm_esc(substr(trim((string)$row['last_message']), 0, 120)); ?></p><?php } ?>
                        </a>
                        <?php } ?>
                    <?php } ?>
                </div>

                <?php if($totalRows > $perPage){ ?>
                <nav class="student-chat-monitor-pagination" aria-label="Conversation pages">
                    <span>Showing <?php echo number_format($offset + 1); ?>-<?php echo number_format(min($totalRows, $offset + $perPage)); ?> of <?php echo number_format($totalRows); ?></span>
                    <div>
                        <?php if($page > 1){ ?><a href="<?php echo scm_esc(scm_page_url($page - 1)); ?>">Previous</a><?php }else{ ?><span>Previous</span><?php } ?>
                        <strong>Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></strong>
                        <?php if($page < $totalPages){ ?><a href="<?php echo scm_esc(scm_page_url($page + 1)); ?>">Next</a><?php }else{ ?><span>Next</span><?php } ?>
                    </div>
                </nav>
                <?php } ?>
            </aside>

            <section class="student-chat-monitor-detail">
                <?php if(!$activeConversation || !$activeRequester || !$activeRecipient){ ?>
                <div class="student-chat-card student-chat-welcome">
                    <span class="student-chat-kicker"><i class="fa fa-comments"></i> Conversation Review</span>
                    <h2>Select a conversation</h2>
                    <p>Choose a reported or active conversation from the left. Opening a conversation is recorded in the moderation audit trail.</p>
                </div>
                <?php }else{ ?>
                <section class="student-chat-card">
                    <div class="student-chat-monitor-detail-head">
                        <div>
                            <span class="student-chat-kicker">Conversation</span>
                            <h2><?php echo scm_esc($activeRequester['display_name']); ?> and <?php echo scm_esc($activeRecipient['display_name']); ?></h2>
                            <p>Started <?php echo scm_esc(scm_time($activeConversation['requestedat'])); ?> · Last update <?php echo scm_esc(scm_time($activeConversation['updatedat'])); ?></p>
                        </div>
                        <span class="<?php echo scm_esc(scm_status_class($activeConversation['status'])); ?>"><?php echo scm_esc(student_chat_status_label($activeConversation['status'])); ?></span>
                    </div>

                    <div class="student-chat-monitor-students">
                        <?php foreach(array($activeRequester, $activeRecipient) as $studentProfile){ ?>
                        <article>
                            <img src="<?php echo scm_esc($studentProfile['photo_src']); ?>" alt="<?php echo scm_esc($studentProfile['display_name']); ?>">
                            <div>
                                <strong><?php echo scm_esc($studentProfile['display_name']); ?></strong>
                                <span><?php echo scm_esc($studentProfile['userid']); ?><?php echo trim((string)$studentProfile['schoolindexnumber']) !== '' ? ' · '.scm_esc($studentProfile['schoolindexnumber']) : ''; ?></span>
                                <small><?php echo scm_esc(trim((string)$studentProfile['class_name']) !== '' ? $studentProfile['class_name'] : 'Class not set'); ?><?php echo trim((string)$studentProfile['housename']) !== '' ? ' · '.scm_esc($studentProfile['housename']) : ''; ?></small>
                            </div>
                        </article>
                        <?php } ?>
                    </div>

                    <?php if(strtolower(trim((string)$activeConversation['status'])) !== 'blocked'){ ?>
                    <form method="post" class="student-chat-monitor-action">
                        <input type="hidden" name="conversationid" value="<?php echo scm_esc($activeConversation['conversationid']); ?>">
                        <label for="block_reason">Block conversation note</label>
                        <input type="text" id="block_reason" name="block_reason" maxlength="180" placeholder="Example: Blocked after review of reported messages">
                        <button type="submit" name="block_conversation" class="student-chat-btn student-chat-btn--danger"><i class="fa fa-ban"></i> Block Conversation</button>
                    </form>
                    <?php }else{ ?>
                    <div class="student-chat-note student-chat-note--danger">
                        <i class="fa fa-ban"></i>
                        <span>This conversation is blocked. <?php echo trim((string)$activeConversation['blockreason']) !== '' ? scm_esc($activeConversation['blockreason']) : 'No block reason was recorded.'; ?></span>
                    </div>
                    <?php } ?>
                </section>

                <section class="student-chat-card">
                    <div class="student-chat-card__head">
                        <div>
                            <span>Reports</span>
                            <h2>Safety reports</h2>
                        </div>
                        <?php if(!empty($activeReports)){ ?>
                        <form method="post">
                            <input type="hidden" name="conversationid" value="<?php echo scm_esc($activeConversation['conversationid']); ?>">
                            <button type="submit" name="mark_conversation_reports_reviewed" class="student-chat-btn student-chat-btn--soft"><i class="fa fa-check"></i> Mark Open Reports Reviewed</button>
                        </form>
                        <?php } ?>
                    </div>
                    <div class="student-chat-monitor-reports">
                        <?php if(empty($activeReports)){ ?>
                        <div class="student-chat-empty student-chat-empty--compact">
                            <h3>No report recorded</h3>
                            <p>This conversation has not been reported by a student.</p>
                        </div>
                        <?php }else{ ?>
                            <?php foreach($activeReports as $reportRow){
                                $reporterName = scm_student_name($reportRow, 'reporter_');
                                $reportedName = scm_student_name($reportRow, 'reported_');
                            ?>
                            <article>
                                <div>
                                    <strong><?php echo scm_esc($reportRow['reason']); ?></strong>
                                    <span>Reported by <?php echo scm_esc($reporterName); ?> against <?php echo scm_esc($reportedName); ?> · <?php echo scm_esc(scm_time($reportRow['datetimeentry'])); ?></span>
                                    <?php if(trim((string)$reportRow['messagetext']) !== ''){ ?><p><?php echo scm_esc($reportRow['messagetext']); ?></p><?php } ?>
                                </div>
                                <div>
                                    <span class="<?php echo strtolower(trim((string)$reportRow['status'])) === 'open' ? 'student-chat-pill student-chat-pill--warning' : 'student-chat-pill student-chat-pill--success'; ?>"><?php echo scm_esc(ucfirst((string)$reportRow['status'])); ?></span>
                                    <?php if(strtolower(trim((string)$reportRow['status'])) === 'open'){ ?>
                                    <form method="post">
                                        <input type="hidden" name="conversationid" value="<?php echo scm_esc($activeConversation['conversationid']); ?>">
                                        <input type="hidden" name="reportid" value="<?php echo scm_esc($reportRow['reportid']); ?>">
                                        <button type="submit" name="mark_report_reviewed" class="student-chat-btn student-chat-btn--soft"><i class="fa fa-check"></i> Reviewed</button>
                                    </form>
                                    <?php } ?>
                                </div>
                            </article>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </section>

                <section class="student-chat-card student-chat-thread">
                    <div class="student-chat-card__head">
                        <div>
                            <span>Messages</span>
                            <h2>Conversation thread</h2>
                        </div>
                    </div>
                    <div class="student-chat-message-list student-chat-message-list--monitor">
                        <?php if(empty($activeMessages)){ ?>
                        <div class="student-chat-empty">
                            <h3>No messages yet</h3>
                            <p>This conversation has no stored messages.</p>
                        </div>
                        <?php }else{ ?>
                            <?php foreach($activeMessages as $messageRow){
                                $isRequester = (string)$messageRow['senderid'] === (string)$activeConversation['requesterid'];
                            ?>
                            <article class="student-chat-message<?php echo !$isRequester ? ' is-mine' : ''; ?>">
                                <div class="student-chat-message__bubble">
                                    <p><?php echo nl2br(scm_esc($messageRow['messagetext'])); ?></p>
                                    <span><?php echo scm_esc($messageRow['sender_name']); ?> · <?php echo scm_esc(scm_time($messageRow['datetimeentry'])); ?><?php echo strtolower(trim((string)$messageRow['status'])) === 'reported' ? ' · Reported' : ''; ?></span>
                                </div>
                            </article>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </section>

                <section class="student-chat-card">
                    <div class="student-chat-card__head">
                        <div>
                            <span>Audit Trail</span>
                            <h2>Admin actions</h2>
                        </div>
                    </div>
                    <div class="student-chat-monitor-audit">
                        <?php if(empty($auditRows)){ ?>
                        <div class="student-chat-empty student-chat-empty--compact">
                            <h3>No admin action yet</h3>
                            <p>The first monitor view is recorded automatically.</p>
                        </div>
                        <?php }else{ ?>
                            <?php foreach($auditRows as $auditRow){ ?>
                            <article>
                                <strong><?php echo scm_esc(ucwords(str_replace('_', ' ', $auditRow['action']))); ?></strong>
                                <span><?php echo scm_esc($auditRow['admin_name']); ?> · <?php echo scm_esc(scm_time($auditRow['datetimeentry'])); ?></span>
                                <?php if(trim((string)$auditRow['reason']) !== ''){ ?><p><?php echo scm_esc($auditRow['reason']); ?></p><?php } ?>
                            </article>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </section>
                <?php } ?>
            </section>
        </div>
    </main>
</body>
</html>
