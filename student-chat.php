<?php
session_start();
$_SESSION['Message'] = isset($_SESSION['Message']) ? $_SESSION['Message'] : '';
include("check-login.php");
include_once("student-chat-utils.php");
student_chat_ensure_tables($con);

if(!student_chat_is_student()){
    header("location:".student_chat_landing_page());
    exit();
}

if(!function_exists('sc_alert')){
function sc_alert($type, $message){
    $type = trim((string)$type);
    if(!in_array($type, array('success','error','warning','info'), true)){
        $type = 'info';
    }
    $icon = 'fa-info-circle';
    if($type === 'success'){ $icon = 'fa-check-circle'; }
    elseif($type === 'error'){ $icon = 'fa-exclamation-circle'; }
    elseif($type === 'warning'){ $icon = 'fa-exclamation-triangle'; }
    return "<div class='student-chat-alert student-chat-alert--".$type."'><i class='fa ".$icon."'></i><span>".student_chat_esc($message)."</span></div>";
}
}

if(!function_exists('sc_redirect')){
function sc_redirect($params = array(), $anchor = ''){
    $query = array();
    foreach($params as $key => $value){
        $key = trim((string)$key);
        $value = trim((string)$value);
        if($key !== '' && $value !== ''){
            $query[] = rawurlencode($key).'='.rawurlencode($value);
        }
    }
    $url = 'student-chat.php'.(!empty($query) ? '?'.implode('&', $query) : '');
    if(trim((string)$anchor) !== ''){
        $url .= '#'.rawurlencode(trim((string)$anchor));
    }
    header("location:".$url);
    exit();
}
}

if(!function_exists('sc_time')){
function sc_time($value){
    $value = trim((string)$value);
    if($value === ''){
        return 'Not yet';
    }
    $time = strtotime($value);
    return $time ? date('d M Y, H:i', $time) : $value;
}
}

if(!function_exists('sc_status_class')){
function sc_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === 'accepted'){ return 'student-chat-pill student-chat-pill--success'; }
    if($status === 'pending'){ return 'student-chat-pill student-chat-pill--warning'; }
    if($status === 'blocked'){ return 'student-chat-pill student-chat-pill--danger'; }
    return 'student-chat-pill student-chat-pill--neutral';
}
}

$studentId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$studentProfile = student_chat_student_profile($con, $studentId);
if(!$studentProfile){
    $_SESSION['Message'] = sc_alert('error', 'Your student profile could not be loaded.');
    header("location:student-page.php");
    exit();
}

$chatSetting = student_chat_get_setting($con);
$studentChatEnabled = (int)$chatSetting['chatenabled'] === 1;
if(!$studentChatEnabled){
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
        $_SESSION['Message'] = sc_alert('warning', 'Student private chat is currently disabled by the school.');
        header("location:student-chat.php");
        exit();
    }
    $flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : '';
    $_SESSION['Message'] = '';
    $disabledNote = trim((string)$chatSetting['disablenote']);
    if($disabledNote === ''){
        $disabledNote = 'Student private chat is currently disabled by the school.';
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/student-chat.css">
</head>
<body class="body-style student-chat-page">
    <div class="header"><?php include("menu.php"); ?></div>
    <main class="student-chat-shell">
        <?php if($flashMessage !== ""){ ?><div class="student-chat-flash"><?php echo $flashMessage; ?></div><?php } ?>
        <section class="student-chat-card student-chat-disabled">
            <div class="student-chat-disabled__inner">
                <span class="student-chat-kicker"><i class="fa fa-lock"></i> Student Private Chat</span>
                <h2>Chat is currently disabled</h2>
                <p><?php echo student_chat_esc($disabledNote); ?></p>
                <a href="student-page.php" class="student-chat-btn student-chat-btn--primary"><i class="fa fa-home"></i> Back to Student Dashboard</a>
            </div>
        </section>
    </main>
</body>
</html>
<?php
    exit();
}

if(isset($_POST['request_chat'])){
    $targetStudent = isset($_POST['target_student']) ? trim((string)$_POST['target_student']) : '';
    $result = student_chat_request($con, $studentId, $targetStudent);
    $_SESSION['Message'] = sc_alert($result['success'] ? 'success' : 'warning', $result['message']);
    if($result['success'] && trim((string)$result['conversationid']) !== ''){
        sc_redirect(array('conversation' => $result['conversationid']));
    }
    sc_redirect();
}

if(isset($_POST['accept_chat'])){
    $conversationId = isset($_POST['conversationid']) ? trim((string)$_POST['conversationid']) : '';
    $ok = student_chat_accept($con, $conversationId, $studentId);
    $_SESSION['Message'] = sc_alert($ok ? 'success' : 'warning', $ok ? 'Chat request accepted.' : 'Chat request could not be accepted.');
    sc_redirect($ok ? array('conversation' => $conversationId) : array());
}

if(isset($_POST['decline_chat'])){
    $conversationId = isset($_POST['conversationid']) ? trim((string)$_POST['conversationid']) : '';
    $ok = student_chat_decline($con, $conversationId, $studentId);
    $_SESSION['Message'] = sc_alert($ok ? 'success' : 'warning', $ok ? 'Chat request declined.' : 'Chat request could not be declined.');
    sc_redirect();
}

if(isset($_POST['cancel_chat'])){
    $conversationId = isset($_POST['conversationid']) ? trim((string)$_POST['conversationid']) : '';
    $ok = student_chat_cancel($con, $conversationId, $studentId);
    $_SESSION['Message'] = sc_alert($ok ? 'success' : 'warning', $ok ? 'Chat request cancelled.' : 'Chat request could not be cancelled.');
    sc_redirect();
}

if(isset($_POST['send_chat'])){
    $conversationId = isset($_POST['conversationid']) ? trim((string)$_POST['conversationid']) : '';
    $messageText = isset($_POST['message_text']) ? trim((string)$_POST['message_text']) : '';
    $result = student_chat_send_message($con, $conversationId, $studentId, $messageText);
    $_SESSION['Message'] = sc_alert($result['success'] ? 'success' : 'warning', $result['message']);
    sc_redirect(array('conversation' => $conversationId), 'chat-thread');
}

if(isset($_POST['block_student'])){
    $targetStudent = isset($_POST['target_student']) ? trim((string)$_POST['target_student']) : '';
    $reason = isset($_POST['block_reason']) ? trim((string)$_POST['block_reason']) : '';
    $ok = student_chat_block_student($con, $studentId, $targetStudent, $reason);
    $_SESSION['Message'] = sc_alert($ok ? 'success' : 'warning', $ok ? 'Student blocked. They can no longer message you privately.' : 'The student could not be blocked.');
    sc_redirect();
}

if(isset($_POST['report_chat'])){
    $conversationId = isset($_POST['conversationid']) ? trim((string)$_POST['conversationid']) : '';
    $messageId = isset($_POST['messageid']) ? trim((string)$_POST['messageid']) : '';
    $reason = isset($_POST['report_reason']) ? trim((string)$_POST['report_reason']) : '';
    $ok = student_chat_report($con, $conversationId, $studentId, $reason, $messageId);
    $_SESSION['Message'] = sc_alert($ok ? 'success' : 'warning', $ok ? 'Report submitted for review.' : 'The report could not be submitted.');
    sc_redirect(array('conversation' => $conversationId), 'chat-thread');
}

$flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : '';
$_SESSION['Message'] = '';

$searchQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$searchScope = isset($_GET['scope']) ? trim((string)$_GET['scope']) : 'all';
if(!in_array($searchScope, array('all','my_class','my_house'), true)){
    $searchScope = 'all';
}
$searchWasRequested = isset($_GET['search_students']) || $searchQuery !== '' || $searchScope !== 'all';
$searchResults = $searchWasRequested ? student_chat_search_students($con, $studentId, $searchQuery, $searchScope, 30) : array();
$conversations = student_chat_list_conversations($con, $studentId, 50);
$pendingInboundCount = student_chat_pending_inbound_count($con, $studentId);

$activeConversationId = isset($_GET['conversation']) ? trim((string)$_GET['conversation']) : '';
$activeConversation = $activeConversationId !== '' ? student_chat_get_conversation_for_student($con, $activeConversationId, $studentId) : null;
$activeMessages = $activeConversation ? student_chat_list_messages($con, $activeConversationId, $studentId, 160) : array();
$activeOther = ($activeConversation && isset($activeConversation['other_profile']) && is_array($activeConversation['other_profile'])) ? $activeConversation['other_profile'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/student-chat.css">
</head>
<body class="body-style student-chat-page">
    <div class="header"><?php include("menu.php"); ?></div>

    <main class="student-chat-shell">
        <?php if($flashMessage !== ""){ ?><div class="student-chat-flash"><?php echo $flashMessage; ?></div><?php } ?>

        <section class="student-chat-hero">
            <div>
                <span class="student-chat-kicker"><i class="fa fa-shield"></i> Student Private Chat</span>
                <h1>Find and chat with fellow students safely.</h1>
                <p>Search for classmates or house mates, send a chat request, and start messaging only after the request is accepted.</p>
            </div>
            <aside>
                <article><span>Pending Requests</span><strong><?php echo (int)$pendingInboundCount; ?></strong></article>
                <article><span>My Conversations</span><strong><?php echo count($conversations); ?></strong></article>
            </aside>
        </section>

        <section class="student-chat-rules">
            <span><i class="fa fa-check-circle"></i> No phone numbers or private records are shown.</span>
            <span><i class="fa fa-check-circle"></i> Chat starts only after acceptance.</span>
            <span><i class="fa fa-check-circle"></i> You can report or block unsafe messages.</span>
        </section>

        <div class="student-chat-layout">
            <aside class="student-chat-sidebar">
                <section class="student-chat-card">
                    <div class="student-chat-card__head">
                        <div>
                            <span>Search Students</span>
                            <h2>Find a student</h2>
                        </div>
                    </div>
                    <form method="get" class="student-chat-search">
                        <label for="q">Name or index number</label>
                        <input type="search" id="q" name="q" value="<?php echo student_chat_esc($searchQuery); ?>" placeholder="Type at least 2 letters">
                        <label for="scope">Filter</label>
                        <select id="scope" name="scope">
                            <option value="all"<?php echo $searchScope === 'all' ? ' selected' : ''; ?>>All students</option>
                            <option value="my_class"<?php echo $searchScope === 'my_class' ? ' selected' : ''; ?>>My current class</option>
                            <option value="my_house"<?php echo $searchScope === 'my_house' ? ' selected' : ''; ?>>My house</option>
                        </select>
                        <button type="submit" name="search_students" value="1"><i class="fa fa-search"></i> Search</button>
                    </form>

                    <div class="student-chat-results">
                        <?php if(!$searchWasRequested){ ?>
                            <div class="student-chat-empty">
                                <h3>Start with search</h3>
                                <p>Type a name, index number, or choose your class or house filter.</p>
                            </div>
                        <?php }elseif(empty($searchResults)){ ?>
                            <div class="student-chat-empty">
                                <h3>No students found</h3>
                                <p>Try another name, or use the class or house filter.</p>
                            </div>
                        <?php }else{ ?>
                            <?php foreach($searchResults as $studentRow){ $relation = $studentRow['relationship']; ?>
                            <article class="student-chat-person">
                                <img src="<?php echo student_chat_esc($studentRow['photo_src']); ?>" alt="<?php echo student_chat_esc($studentRow['display_name']); ?>">
                                <div class="student-chat-person__body">
                                    <strong><?php echo student_chat_esc($studentRow['display_name']); ?></strong>
                                    <span><?php echo student_chat_esc($studentRow['class_name'] !== '' ? $studentRow['class_name'] : 'Class not set'); ?><?php echo $studentRow['housename'] !== '' ? ' - '.student_chat_esc($studentRow['housename']) : ''; ?></span>
                                    <div class="student-chat-person__actions">
                                        <?php if($relation['state'] === 'accepted'){ ?>
                                            <a href="student-chat.php?conversation=<?php echo rawurlencode($relation['conversationid']); ?>">Open Chat</a>
                                        <?php }elseif($relation['state'] === 'incoming_pending'){ ?>
                                            <a href="student-chat.php?conversation=<?php echo rawurlencode($relation['conversationid']); ?>">Respond</a>
                                        <?php }elseif($relation['state'] === 'outgoing_pending'){ ?>
                                            <span class="student-chat-muted">Request sent</span>
                                        <?php }elseif($relation['state'] === 'blocked_by_me' || $relation['state'] === 'blocked_me'){ ?>
                                            <span class="student-chat-muted"><?php echo student_chat_esc($relation['label']); ?></span>
                                        <?php }else{ ?>
                                            <form method="post">
                                                <input type="hidden" name="target_student" value="<?php echo student_chat_esc($studentRow['userid']); ?>">
                                                <button type="submit" name="request_chat"><i class="fa fa-user-plus"></i> Request Chat</button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </div>
                            </article>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </section>

                <section class="student-chat-card">
                    <div class="student-chat-card__head">
                        <div>
                            <span>Conversations</span>
                            <h2>My chats</h2>
                        </div>
                    </div>
                    <div class="student-chat-list">
                        <?php if(empty($conversations)){ ?>
                            <div class="student-chat-empty student-chat-empty--compact">
                                <h3>No chats yet</h3>
                                <p>Search for a student and send a request to begin.</p>
                            </div>
                        <?php }else{ ?>
                            <?php foreach($conversations as $conversationRow){
                                $otherProfile = isset($conversationRow['other_profile']) ? $conversationRow['other_profile'] : null;
                                if(!$otherProfile){ continue; }
                                $isActive = $activeConversation && (string)$activeConversation['conversationid'] === (string)$conversationRow['conversationid'];
                            ?>
                            <a class="student-chat-thread-link<?php echo $isActive ? ' is-active' : ''; ?>" href="student-chat.php?conversation=<?php echo rawurlencode((string)$conversationRow['conversationid']); ?>">
                                <img src="<?php echo student_chat_esc($otherProfile['photo_src']); ?>" alt="<?php echo student_chat_esc($otherProfile['display_name']); ?>">
                                <span>
                                    <strong><?php echo student_chat_esc($otherProfile['display_name']); ?></strong>
                                    <small><?php echo student_chat_esc(student_chat_status_label($conversationRow['status'])); ?></small>
                                    <?php if(trim((string)$conversationRow['last_message']) !== ''){ ?><em><?php echo student_chat_esc(substr(trim((string)$conversationRow['last_message']), 0, 70)); ?></em><?php } ?>
                                </span>
                            </a>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </section>
            </aside>

            <section class="student-chat-main">
                <?php if(!$activeConversation || !$activeOther){ ?>
                    <div class="student-chat-card student-chat-welcome">
                        <span class="student-chat-kicker"><i class="fa fa-comments"></i> Private Chat</span>
                        <h2>Select a conversation</h2>
                        <p>Open an existing chat or search for a fellow student and send a request. Conversations remain private between the two students, but reported messages can be reviewed by the school.</p>
                    </div>
                <?php }else{ ?>
                    <div class="student-chat-card student-chat-thread" id="chat-thread">
                        <div class="student-chat-thread__head">
                            <div class="student-chat-thread__student">
                                <img src="<?php echo student_chat_esc($activeOther['photo_src']); ?>" alt="<?php echo student_chat_esc($activeOther['display_name']); ?>">
                                <div>
                                    <span class="student-chat-kicker">Chat with</span>
                                    <h2><?php echo student_chat_esc($activeOther['display_name']); ?></h2>
                                    <p><?php echo student_chat_esc($activeOther['class_name'] !== '' ? $activeOther['class_name'] : 'Class not set'); ?><?php echo $activeOther['housename'] !== '' ? ' - '.student_chat_esc($activeOther['housename']) : ''; ?></p>
                                </div>
                            </div>
                            <span class="<?php echo student_chat_esc(sc_status_class($activeConversation['status'])); ?>"><?php echo student_chat_esc(student_chat_status_label($activeConversation['status'])); ?></span>
                        </div>

                        <?php if(strtolower(trim((string)$activeConversation['status'])) === 'pending'){ ?>
                            <div class="student-chat-request-box">
                                <?php if((string)$activeConversation['recipientid'] === $studentId){ ?>
                                    <h3><?php echo student_chat_esc($activeOther['display_name']); ?> wants to chat with you.</h3>
                                    <p>Accept only if you are comfortable starting a private school chat.</p>
                                    <div class="student-chat-inline-actions">
                                        <form method="post">
                                            <input type="hidden" name="conversationid" value="<?php echo student_chat_esc($activeConversation['conversationid']); ?>">
                                            <button type="submit" name="accept_chat" class="student-chat-btn student-chat-btn--primary"><i class="fa fa-check"></i> Accept Request</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="conversationid" value="<?php echo student_chat_esc($activeConversation['conversationid']); ?>">
                                            <button type="submit" name="decline_chat" class="student-chat-btn student-chat-btn--soft"><i class="fa fa-times"></i> Decline</button>
                                        </form>
                                    </div>
                                <?php }else{ ?>
                                    <h3>Waiting for approval.</h3>
                                    <p>Your chat request has been sent. Messaging opens after the other student accepts.</p>
                                    <form method="post">
                                        <input type="hidden" name="conversationid" value="<?php echo student_chat_esc($activeConversation['conversationid']); ?>">
                                        <button type="submit" name="cancel_chat" class="student-chat-btn student-chat-btn--soft"><i class="fa fa-times"></i> Cancel Request</button>
                                    </form>
                                <?php } ?>
                            </div>
                        <?php }elseif(strtolower(trim((string)$activeConversation['status'])) === 'accepted'){ ?>
                            <div class="student-chat-message-list">
                                <?php if(empty($activeMessages)){ ?>
                                    <div class="student-chat-empty">
                                        <h3>No messages yet</h3>
                                        <p>Send a respectful message to begin the conversation.</p>
                                    </div>
                                <?php }else{ ?>
                                    <?php foreach($activeMessages as $messageRow){
                                        $isMine = (string)$messageRow['senderid'] === $studentId;
                                    ?>
                                    <article class="student-chat-message<?php echo $isMine ? ' is-mine' : ''; ?>">
                                        <div class="student-chat-message__bubble">
                                            <p><?php echo nl2br(student_chat_esc($messageRow['messagetext'])); ?></p>
                                            <span><?php echo student_chat_esc($isMine ? 'You' : $messageRow['sender_name']); ?> - <?php echo student_chat_esc(sc_time($messageRow['datetimeentry'])); ?><?php echo strtolower(trim((string)$messageRow['status'])) === 'reported' ? ' - Reported' : ''; ?></span>
                                            <?php if(!$isMine){ ?>
                                            <form method="post" class="student-chat-message-report">
                                                <input type="hidden" name="conversationid" value="<?php echo student_chat_esc($activeConversation['conversationid']); ?>">
                                                <input type="hidden" name="messageid" value="<?php echo student_chat_esc($messageRow['messageid']); ?>">
                                                <input type="hidden" name="report_reason" value="Reported from private chat message">
                                                <button type="submit" name="report_chat"><i class="fa fa-flag"></i> Report message</button>
                                            </form>
                                            <?php } ?>
                                        </div>
                                    </article>
                                    <?php } ?>
                                <?php } ?>
                            </div>

                            <form method="post" class="student-chat-composer">
                                <input type="hidden" name="conversationid" value="<?php echo student_chat_esc($activeConversation['conversationid']); ?>">
                                <label for="message_text">Write a message</label>
                                <textarea id="message_text" name="message_text" rows="4" placeholder="Type a respectful message..." required></textarea>
                                <button type="submit" name="send_chat"><i class="fa fa-send"></i> Send Message</button>
                            </form>
                        <?php }elseif(strtolower(trim((string)$activeConversation['status'])) === 'blocked'){ ?>
                            <div class="student-chat-request-box student-chat-request-box--danger">
                                <h3>This chat is blocked.</h3>
                                <p>Private messages are no longer allowed in this conversation.</p>
                            </div>
                        <?php }else{ ?>
                            <div class="student-chat-request-box">
                                <h3>This request is closed.</h3>
                                <p>You can search for the student and send a new chat request if appropriate.</p>
                            </div>
                        <?php } ?>
                    </div>

                    <section class="student-chat-card student-chat-safety">
                        <div>
                            <span class="student-chat-kicker"><i class="fa fa-shield"></i> Safety Controls</span>
                            <h2>Report or block</h2>
                            <p>Use these only when a message is inappropriate, threatening, or makes you uncomfortable.</p>
                        </div>
                        <div class="student-chat-safety__forms">
                            <form method="post">
                                <input type="hidden" name="conversationid" value="<?php echo student_chat_esc($activeConversation['conversationid']); ?>">
                                <label for="report_reason">Report reason</label>
                                <select id="report_reason" name="report_reason" required>
                                    <option value="">Select a reason</option>
                                    <option value="Inappropriate language">Inappropriate language</option>
                                    <option value="Bullying or harassment">Bullying or harassment</option>
                                    <option value="Threatening message">Threatening message</option>
                                    <option value="Unwanted messages">Unwanted messages</option>
                                    <option value="Other safety concern">Other safety concern</option>
                                </select>
                                <button type="submit" name="report_chat" class="student-chat-btn student-chat-btn--soft"><i class="fa fa-flag"></i> Report Conversation</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="target_student" value="<?php echo student_chat_esc($activeOther['userid']); ?>">
                                <label for="block_reason">Block note</label>
                                <input type="text" id="block_reason" name="block_reason" placeholder="Optional reason">
                                <button type="submit" name="block_student" class="student-chat-btn student-chat-btn--danger"><i class="fa fa-ban"></i> Block Student</button>
                            </form>
                        </div>
                    </section>
                <?php } ?>
            </section>
        </div>
    </main>
</body>
</html>
