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

if(!function_exists('scs_esc')){
function scs_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if(!function_exists('scs_alert')){
function scs_alert($type, $message){
    $type = trim((string)$type);
    if(!in_array($type, array('success','error','warning','info'), true)){
        $type = 'info';
    }
    return "<div class='student-chat-alert student-chat-alert--".$type."'><i class='fa fa-info-circle'></i><span>".scs_esc($message)."</span></div>";
}
}

if(isset($_POST['save_student_chat_setting'])){
    $enabled = isset($_POST['chatenabled']) && (string)$_POST['chatenabled'] === '1' ? 1 : 0;
    $disableNote = isset($_POST['disablenote']) ? trim((string)$_POST['disablenote']) : '';
    if($enabled === 0 && $disableNote === ''){
        $disableNote = 'Student private chat is currently disabled by the school.';
    }
    $saved = student_chat_update_setting($con, $enabled, $disableNote, isset($_SESSION['USERID']) ? $_SESSION['USERID'] : '');
    $_SESSION['Message'] = scs_alert($saved ? 'success' : 'error', $saved ? 'Student chat setting updated.' : 'Student chat setting could not be updated.');
    header("location:student-chat-settings.php");
    exit();
}

$setting = student_chat_get_setting($con);
$chatEnabled = (int)$setting['chatenabled'] === 1;
$disableNote = trim((string)$setting['disablenote']);

$stats = array(
    'active' => 0,
    'pending' => 0,
    'messages' => 0,
    'reports' => 0,
    'blocks' => 0
);
$statsRes = mysqli_query($con, "SELECT
        SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) AS active_total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_total
    FROM tblstudentchatconversation");
if($statsRes && ($row = mysqli_fetch_array($statsRes, MYSQLI_ASSOC))){
    $stats['active'] = (int)$row['active_total'];
    $stats['pending'] = (int)$row['pending_total'];
}
$msgRes = mysqli_query($con, "SELECT COUNT(*) AS total_messages FROM tblstudentchatmessage WHERE status<>'deleted'");
if($msgRes && ($row = mysqli_fetch_array($msgRes, MYSQLI_ASSOC))){
    $stats['messages'] = (int)$row['total_messages'];
}
$reportRes = mysqli_query($con, "SELECT COUNT(*) AS total_reports FROM tblstudentchatreport WHERE status='open'");
if($reportRes && ($row = mysqli_fetch_array($reportRes, MYSQLI_ASSOC))){
    $stats['reports'] = (int)$row['total_reports'];
}
$blockRes = mysqli_query($con, "SELECT COUNT(*) AS total_blocks
    FROM (
        SELECT LEAST(blockerid, blockedid) AS student_a, GREATEST(blockerid, blockedid) AS student_b
        FROM tblstudentchatblock
        WHERE status='active'
        UNION
        SELECT LEAST(requesterid, recipientid) AS student_a, GREATEST(requesterid, recipientid) AS student_b
        FROM tblstudentchatconversation
        WHERE status='blocked'
    ) blocked_pairs");
if($blockRes && ($row = mysqli_fetch_array($blockRes, MYSQLI_ASSOC))){
    $stats['blocks'] = (int)$row['total_blocks'];
}

$flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : '';
$_SESSION['Message'] = '';
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

        <section class="student-chat-hero student-chat-hero--settings">
            <div>
                <span class="student-chat-kicker"><i class="fa fa-sliders"></i> Student Chat Control</span>
                <h1>Turn student private chat on or off.</h1>
                <p>Use this switch when the school wants to pause student-to-student private messaging. Existing records stay safe in the system.</p>
            </div>
            <aside>
                <article><span>Current Status</span><strong><?php echo $chatEnabled ? 'On' : 'Off'; ?></strong></article>
                <article><span>Open Reports</span><strong><?php echo (int)$stats['reports']; ?></strong></article>
            </aside>
        </section>

        <div class="student-chat-settings-grid">
            <section class="student-chat-card student-chat-settings-card">
                <div class="student-chat-card__head">
                    <div>
                        <span>School Switch</span>
                        <h2>Private chat availability</h2>
                    </div>
                    <span class="<?php echo $chatEnabled ? 'student-chat-pill student-chat-pill--success' : 'student-chat-pill student-chat-pill--danger'; ?>"><?php echo $chatEnabled ? 'Enabled' : 'Disabled'; ?></span>
                </div>

                <form method="post" class="student-chat-settings-form">
                    <label class="student-chat-toggle">
                        <input type="radio" name="chatenabled" value="1"<?php echo $chatEnabled ? ' checked' : ''; ?>>
                        <span>
                            <strong>Enable student private chat</strong>
                            <small>Students can search, request, accept, and send private messages.</small>
                        </span>
                    </label>
                    <label class="student-chat-toggle">
                        <input type="radio" name="chatenabled" value="0"<?php echo !$chatEnabled ? ' checked' : ''; ?>>
                        <span>
                            <strong>Disable student private chat</strong>
                            <small>Students will see a school-disabled message if they try to open the chat page.</small>
                        </span>
                    </label>

                    <label for="disablenote">Message shown when disabled</label>
                    <textarea id="disablenote" name="disablenote" rows="3" maxlength="180" placeholder="Student private chat is currently disabled by the school."><?php echo scs_esc($disableNote); ?></textarea>

                    <button type="submit" name="save_student_chat_setting" class="student-chat-btn student-chat-btn--primary"><i class="fa fa-save"></i> Save Setting</button>
                </form>
            </section>

            <section class="student-chat-card">
                <div class="student-chat-card__head">
                    <div>
                        <span>Quick Snapshot</span>
                        <h2>Chat activity</h2>
                    </div>
                </div>
                <div class="student-chat-settings-stats">
                    <article><span>Active Chats</span><strong><?php echo (int)$stats['active']; ?></strong></article>
                    <article><span>Pending Requests</span><strong><?php echo (int)$stats['pending']; ?></strong></article>
                    <article><span>Messages Stored</span><strong><?php echo (int)$stats['messages']; ?></strong></article>
                    <article><span>Blocked Pairs</span><strong><?php echo (int)$stats['blocks']; ?></strong></article>
                </div>
                <div class="student-chat-note">
                    <i class="fa fa-info-circle"></i>
                    <span>Turning chat off does not delete existing conversations, reports, or blocked records. It only pauses student access.</span>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
