<?php
session_start();
include("dbstring.php");
include("check-login.php");
include("code.php");
include_once("class-teacher-utils.php");
include_once("house-master-utils.php");
ensure_class_teacher_table($con);
ensure_house_tables($con);

if(!function_exists('msg_esc')){
function msg_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if(!function_exists('msg_time')){
function msg_time($value){
    $time = strtotime((string)$value);
    return $time ? date("d M Y, H:i", $time) : (string)$value;
}
}

if(!function_exists('msg_audience_badge_class')){
function msg_audience_badge_class($audience){
    $audience = um_message_normalize_audience($audience);
    if($audience === 'students'){
        return 'messages-audience messages-audience--students';
    }
    if($audience === 'teachers'){
        return 'messages-audience messages-audience--teachers';
    }
    if($audience === 'admins'){
        return 'messages-audience messages-audience--admins';
    }
    return 'messages-audience messages-audience--all';
}
}

if(!function_exists('msg_person_name')){
function msg_person_name($row){
    $full = trim(
        (isset($row['firstname']) ? (string)$row['firstname'] : '') . ' ' .
        (isset($row['othernames']) ? (string)$row['othernames'] : '') . ' ' .
        (isset($row['surname']) ? (string)$row['surname'] : '')
    );
    return $full !== '' ? $full : (isset($row['userid']) ? (string)$row['userid'] : '');
}
}

if(!function_exists('msg_target_key')){
function msg_target_key($type, $group, $value){
    return trim((string)$type) . '|' . trim((string)$group) . '|' . trim((string)$value);
}
}

if(!function_exists('msg_add_target_option')){
function msg_add_target_option(&$options, $type, $group, $value, $label, $badgeLabel = ''){
    $key = msg_target_key($type, $group, $value);
    if(isset($options[$key])){
        return;
    }
    $options[$key] = array(
        'recipient_type' => trim((string)$type),
        'recipient_group' => trim((string)$group),
        'recipient_value' => trim((string)$value),
        'recipient_label' => trim((string)$label),
        'badge_label' => trim((string)$badgeLabel)
    );
}
}

if(!function_exists('msg_student_current_class_teacher_options')){
function msg_student_current_class_teacher_options($con, $studentId){
    $studentIdEsc = mysqli_real_escape_string($con, (string)$studentId);
    $options = array();
    $sql = "SELECT
                ct.userid AS recipient_userid,
                su.userid,
                su.firstname,
                su.othernames,
                su.surname,
                ce.class_name,
                bh.batch,
                tr.termname
            FROM tbltermregistry tr
            INNER JOIN tblclassteacher ct
                ON ct.classid=tr.class_entryid
               AND ct.batchid=tr.batchid
               AND ct.termname=tr.termname
               AND ct.status='active'
            INNER JOIN tblsystemuser su
                ON su.userid=ct.userid
               AND su.status='active'
            LEFT JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
            LEFT JOIN tblbatch bh ON bh.batchid=tr.batchid
            WHERE tr.userid='$studentIdEsc'
            ORDER BY bh.datetimeentry DESC, tr.termname DESC, ct.datetimeentry DESC
            LIMIT 6";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $teacherId = trim((string)$row['recipient_userid']);
            if($teacherId === ''){
                continue;
            }
            $teacherName = msg_person_name($row);
            $classLabel = trim((string)$row['class_name']);
            $batchLabel = trim((string)$row['batch']);
            $termLabel = trim((string)$row['termname']);
            $label = "My Class Teacher";
            if($teacherName !== ''){
                $label .= " - ".$teacherName;
            }
            if($classLabel !== '' || $batchLabel !== '' || $termLabel !== ''){
                $label .= " (".trim($classLabel.($batchLabel !== '' ? " · ".$batchLabel : '').($termLabel !== '' ? " · Semester ".$termLabel : '')).")";
            }
            msg_add_target_option($options, 'user', 'teachers', $teacherId, $label, 'Direct');
        }
    }
    return $options;
}
}

if(!function_exists('msg_student_house_master_options')){
function msg_student_house_master_options($con, $studentId){
    $studentIdEsc = mysqli_real_escape_string($con, (string)$studentId);
    $options = array();
    $sql = "SELECT
                hm.userid AS recipient_userid,
                su.userid,
                su.firstname,
                su.othernames,
                su.surname,
                h.housename
            FROM tblstudenthouse sh
            INNER JOIN tblhousemaster hm
                ON hm.houseid=sh.houseid
               AND hm.status='active'
            INNER JOIN tblsystemuser su
                ON su.userid=hm.userid
               AND su.status='active'
            INNER JOIN tblhouse h
                ON h.houseid=sh.houseid
               AND h.status='active'
            WHERE sh.userid='$studentIdEsc'
              AND sh.status='active'
            ORDER BY hm.datetimeentry DESC
            LIMIT 6";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $teacherId = trim((string)$row['recipient_userid']);
            if($teacherId === ''){
                continue;
            }
            $teacherName = msg_person_name($row);
            $houseLabel = trim((string)$row['housename']);
            $label = "My House Master / Mistress";
            if($teacherName !== ''){
                $label .= " - ".$teacherName;
            }
            if($houseLabel !== ''){
                $label .= " (".$houseLabel.")";
            }
            msg_add_target_option($options, 'user', 'teachers', $teacherId, $label, 'Direct');
        }
    }
    return $options;
}
}

if(!function_exists('msg_teacher_class_scope_options')){
function msg_teacher_class_scope_options($con, $teacherId){
    $teacherIdEsc = mysqli_real_escape_string($con, (string)$teacherId);
    $options = array();
    $sql = "SELECT ct.classid,ct.batchid,ct.termname,ce.class_name,bh.batch
            FROM tblclassteacher ct
            INNER JOIN tblclassentry ce ON ce.class_entryid=ct.classid
            LEFT JOIN tblbatch bh ON bh.batchid=ct.batchid
            WHERE ct.userid='$teacherIdEsc' AND ct.status='active'
            ORDER BY bh.datetimeentry DESC, ct.termname DESC, ce.class_name ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $payload = trim((string)$row['classid'])."|".trim((string)$row['batchid'])."|".trim((string)$row['termname']);
            $label = "My Class Students";
            $classLabel = trim((string)$row['class_name']);
            $batchLabel = trim((string)$row['batch']);
            $termLabel = trim((string)$row['termname']);
            if($classLabel !== '' || $batchLabel !== '' || $termLabel !== ''){
                $label .= " (".trim($classLabel.($batchLabel !== '' ? " · ".$batchLabel : '').($termLabel !== '' ? " · Semester ".$termLabel : '')).")";
            }
            msg_add_target_option($options, 'class_scope', 'students', $payload, $label, 'Class');
        }
    }
    return $options;
}
}

if(!function_exists('msg_teacher_house_scope_options')){
function msg_teacher_house_scope_options($con, $teacherId){
    $teacherIdEsc = mysqli_real_escape_string($con, (string)$teacherId);
    $options = array();
    $sql = "SELECT hm.houseid,h.housename
            FROM tblhousemaster hm
            INNER JOIN tblhouse h ON h.houseid=hm.houseid
            WHERE hm.userid='$teacherIdEsc' AND hm.status='active' AND h.status='active'
            ORDER BY h.housename ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $houseId = trim((string)$row['houseid']);
            if($houseId === ''){
                continue;
            }
            $label = "My House Students";
            $houseLabel = trim((string)$row['housename']);
            if($houseLabel !== ''){
                $label .= " (".$houseLabel.")";
            }
            msg_add_target_option($options, 'house_scope', 'students', $houseId, $label, 'House');
        }
    }
    return $options;
}
}

if(!function_exists('msg_target_options_for_current_user')){
function msg_target_options_for_current_user($con, $userId, $systemType){
    $options = array();
    $systemType = trim((string)$systemType);

    if($systemType === 'Student'){
        msg_add_target_option($options, 'group', 'admins', '', 'Admin Only', 'Admin');
        foreach(msg_student_current_class_teacher_options($con, $userId) as $key => $meta){
            $options[$key] = $meta;
        }
        foreach(msg_student_house_master_options($con, $userId) as $key => $meta){
            $options[$key] = $meta;
        }
        return $options;
    }

    if($systemType === 'Teacher'){
        msg_add_target_option($options, 'group', 'admins', '', 'Admin Only', 'Admin');
        foreach(msg_teacher_class_scope_options($con, $userId) as $key => $meta){
            $options[$key] = $meta;
        }
        foreach(msg_teacher_house_scope_options($con, $userId) as $key => $meta){
            $options[$key] = $meta;
        }
        return $options;
    }

    msg_add_target_option($options, 'group', 'all', '', 'Everyone', 'Everyone');
    msg_add_target_option($options, 'group', 'students', '', 'Students Only', 'Students');
    msg_add_target_option($options, 'group', 'teachers', '', 'Teachers Only', 'Teachers');
    msg_add_target_option($options, 'group', 'admins', '', 'Admin Only', 'Admin');
    return $options;
}
}

if(!function_exists('msg_default_target_key')){
function msg_default_target_key($options){
    if(isset($options[msg_target_key('group', 'admins', '')])){
        return msg_target_key('group', 'admins', '');
    }
    reset($options);
    return key($options);
}
}

if(!function_exists('msg_target_badge_class')){
function msg_target_badge_class($recipientType, $recipientGroup){
    $recipientType = trim((string)$recipientType);
    if($recipientType === 'user'){
        return 'messages-audience messages-audience--direct';
    }
    if($recipientType === 'class_scope'){
        return 'messages-audience messages-audience--class';
    }
    if($recipientType === 'house_scope'){
        return 'messages-audience messages-audience--house';
    }
    return msg_audience_badge_class($recipientGroup);
}
}

if(!function_exists('msg_target_badge_label')){
function msg_target_badge_label($recipientType, $recipientGroup, $recipientLabel){
    $recipientType = trim((string)$recipientType);
    if($recipientType === 'user'){
        return 'Direct';
    }
    if($recipientType === 'class_scope'){
        return 'Class';
    }
    if($recipientType === 'house_scope'){
        return 'House';
    }
    if(trim((string)$recipientLabel) !== ''){
        return trim((string)$recipientLabel);
    }
    return um_message_audience_label($recipientGroup);
}
}

if(!function_exists('msg_target_label')){
function msg_target_label($recipientType, $recipientGroup, $recipientLabel){
    $recipientType = trim((string)$recipientType);
    if($recipientType === 'group'){
        return um_message_audience_label($recipientGroup);
    }
    if(trim((string)$recipientLabel) !== ''){
        return trim((string)$recipientLabel);
    }
    return um_message_audience_label($recipientGroup);
}
}

if(!function_exists('msg_visibility_where_sql')){
function msg_visibility_where_sql($con, $userId, $systemType){
    $userIdEsc = mysqli_real_escape_string($con, (string)$userId);
    $systemType = trim((string)$systemType);

    if($systemType === 'Student'){
        return "(mg.sentby='$userIdEsc'
            OR (COALESCE(mg.recipient_type,'group')='group' AND COALESCE(mg.recipient_group,'all') IN ('all','students'))
            OR (COALESCE(mg.recipient_type,'group')='user' AND mg.recipient_value='$userIdEsc')
            OR (COALESCE(mg.recipient_type,'group')='class_scope' AND EXISTS (
                SELECT 1
                FROM tbltermregistry tr
                WHERE tr.userid='$userIdEsc'
                  AND CONCAT(tr.class_entryid,'|',tr.batchid,'|',tr.termname)=mg.recipient_value
            ))
            OR (COALESCE(mg.recipient_type,'group')='house_scope' AND EXISTS (
                SELECT 1
                FROM tblstudenthouse sh
                WHERE sh.userid='$userIdEsc'
                  AND sh.status='active'
                  AND sh.houseid=mg.recipient_value
            )))";
    }

    if($systemType === 'Teacher'){
        return "(mg.sentby='$userIdEsc'
            OR (COALESCE(mg.recipient_type,'group')='group' AND COALESCE(mg.recipient_group,'all') IN ('all','teachers'))
            OR (COALESCE(mg.recipient_type,'group')='user' AND mg.recipient_value='$userIdEsc'))";
    }

    return "1=1";
}
}

$__CurrentUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
$__CurrentUserIdEsc = mysqli_real_escape_string($con, $__CurrentUserId);
$__CanManageAllMessages = um_is_admin_manager();
$__SystemType = isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
$__UserFullName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : '';
$__UserDisplayName = $__UserFullName !== '' ? $__UserFullName : (isset($_SESSION['USERNAME']) ? trim((string)$_SESSION['USERNAME']) : $__CurrentUserId);
$__TargetOptions = msg_target_options_for_current_user($con, $__CurrentUserId, $__SystemType);
$__DefaultTarget = msg_default_target_key($__TargetOptions);
$__VisibilitySql = msg_visibility_where_sql($con, $__CurrentUserId, $__SystemType);
if($__CurrentUserId !== ''){
    um_mark_messages_seen($con, $__CurrentUserId);
}
$__RoleLabel = 'School Communication';
if($__SystemType === 'Student'){
    $__RoleLabel = 'Student Message Box';
} elseif($__SystemType === 'Teacher'){
    $__RoleLabel = 'Teacher Message Box';
} elseif($__SystemType === 'Headmaster'){
    $__RoleLabel = 'Headmaster Message Box';
} elseif($__SystemType === 'User'){
    $__RoleLabel = 'Office Message Box';
} elseif($__SystemType === 'normal_user' || $__SystemType === 'super_user'){
    $__RoleLabel = 'Admin Message Box';
}

if(isset($_POST["send_message"])){
    $_Message = trim((string)(isset($_POST['message']) ? $_POST['message'] : ''));
    if($_Message === ''){
        $_SESSION['Message'] = "<div style='color:#991b1b;padding:10px;'>Please type a message before sending.</div>";
    } else {
        $_TargetKey = isset($_POST['message_target']) ? trim((string)$_POST['message_target']) : $__DefaultTarget;
        if($_TargetKey === '' || !isset($__TargetOptions[$_TargetKey])){
            $_TargetKey = $__DefaultTarget;
        }
        $_TargetMeta = isset($__TargetOptions[$_TargetKey]) ? $__TargetOptions[$_TargetKey] : array(
            'recipient_type' => 'group',
            'recipient_group' => 'admins',
            'recipient_value' => '',
            'recipient_label' => 'Admin Only'
        );
        $_MessageId = mysqli_real_escape_string($con, (string)$code);
        $_MessageEsc = mysqli_real_escape_string($con, $_Message);
        $_RecipientGroupEsc = mysqli_real_escape_string($con, (string)$_TargetMeta['recipient_group']);
        $_RecipientTypeEsc = mysqli_real_escape_string($con, (string)$_TargetMeta['recipient_type']);
        $_RecipientValueEsc = mysqli_real_escape_string($con, (string)$_TargetMeta['recipient_value']);
        $_RecipientLabelEsc = mysqli_real_escape_string($con, (string)$_TargetMeta['recipient_label']);
        $_SQL = mysqli_query($con, "INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group,recipient_type,recipient_value,recipient_label)
            VALUES('$_MessageId','$_MessageEsc',NOW(),'active','$__CurrentUserIdEsc','$_RecipientGroupEsc','$_RecipientTypeEsc','$_RecipientValueEsc','$_RecipientLabelEsc')");
        if($_SQL){
            if($__SystemType === 'Teacher'){
                engagement_track_daily_action($con, 'teacher_message_sent_daily', $__CurrentUserId);
            } elseif($__SystemType === 'Student'){
                engagement_track_daily_action($con, 'student_message_sent_daily', $__CurrentUserId);
            }
            $_SESSION['Message'] = "<div style='color:#166534;padding:10px;'>Message successfully sent.</div>";
        } else {
            $_SESSION['Message'] = "<div style='color:#991b1b;padding:10px;'>Message failed to send.</div>";
        }
    }
    header("location:messages.php");
    exit();
}

$__DeleteMessageId = "";
if(isset($_POST['delete_message'])){
    $__DeleteMessageId = trim((string)(isset($_POST['messageid']) ? $_POST['messageid'] : ""));
} elseif(isset($_GET['delete_message'])){
    $__DeleteMessageId = trim((string)$_GET['delete_message']);
}
if($__DeleteMessageId !== ""){
    $_MessageIdEsc = mysqli_real_escape_string($con, $__DeleteMessageId);
    $_DeleteWhere = $__CanManageAllMessages ? "messageid='$_MessageIdEsc'" : "messageid='$_MessageIdEsc' AND sentby='$__CurrentUserIdEsc'";
    $_SQL_D = mysqli_query($con, "DELETE FROM tblmessages WHERE $_DeleteWhere LIMIT 1");
    if($_SQL_D && mysqli_affected_rows($con) > 0){
        $_SESSION['Message'] = "<div style='color:#991b1b;padding:10px;'>Message deleted.</div>";
    } else {
        $_SESSION['Message'] = "<div style='color:#991b1b;padding:10px;'>Message could not be deleted.</div>";
    }
    header("location:messages.php");
    exit();
}

$flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";
$_SESSION['Message'] = "";

$myMessageCount = 0;
$boardMessageCount = 0;
$myMessages = array();
$boardMessages = array();

$_SQL_MY_COUNT = mysqli_query($con, "SELECT COUNT(*) AS total_messages FROM tblmessages WHERE sentby='$__CurrentUserIdEsc' AND status='active'");
if($_SQL_MY_COUNT && ($row_count = mysqli_fetch_array($_SQL_MY_COUNT, MYSQLI_ASSOC))){
    $myMessageCount = (int)$row_count['total_messages'];
}

$_SQL_BOARD_COUNT = mysqli_query($con, "SELECT COUNT(*) AS total_messages
    FROM tblmessages mg
    WHERE mg.status='active' AND $__VisibilitySql");
if($_SQL_BOARD_COUNT && ($row_board_count = mysqli_fetch_array($_SQL_BOARD_COUNT, MYSQLI_ASSOC))){
    $boardMessageCount = (int)$row_board_count['total_messages'];
}

$_SQL_MY_MESSAGES = mysqli_query($con, "SELECT messageid,messages,datetimeentry,recipient_group
        ,COALESCE(recipient_type,'group') AS recipient_type,COALESCE(recipient_value,'') AS recipient_value,COALESCE(recipient_label,'') AS recipient_label
    FROM tblmessages
    WHERE sentby='$__CurrentUserIdEsc' AND status='active'
    ORDER BY datetimeentry DESC
    LIMIT 40");
if($_SQL_MY_MESSAGES){
    while($row = mysqli_fetch_array($_SQL_MY_MESSAGES, MYSQLI_ASSOC)){
        $myMessages[] = $row;
    }
}

$_SQL_BOARD_MESSAGES = mysqli_query($con, "SELECT
        mg.messageid,
        mg.messages,
        mg.datetimeentry,
        mg.recipient_group,
        COALESCE(mg.recipient_type,'group') AS recipient_type,
        COALESCE(mg.recipient_value,'') AS recipient_value,
        COALESCE(mg.recipient_label,'') AS recipient_label,
        mg.sentby,
        su.firstname,
        su.othernames,
        su.surname,
        su.systemtype
    FROM tblmessages mg
    INNER JOIN tblsystemuser su ON mg.sentby=su.userid
    WHERE mg.status='active' AND $__VisibilitySql
    ORDER BY mg.datetimeentry DESC
    LIMIT 60");
if($_SQL_BOARD_MESSAGES){
    while($row = mysqli_fetch_array($_SQL_BOARD_MESSAGES, MYSQLI_ASSOC)){
        $boardMessages[] = $row;
    }
}

$visibilityHint = "You can see all active message groups here.";
if($__SystemType === 'Student'){
    $visibilityHint = "You can message admin, your assigned class teacher, and your house master or mistress here. You will also see messages sent to your class or house.";
} elseif($__SystemType === 'Teacher'){
    $visibilityHint = "You can message admin, send updates to your assigned class students, and send house notices to your assigned house students from here.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/messages.css">
</head>
<body class="body-style messages-page">
    <div class="header">
    <?php include("menu.php"); ?>
    </div>

    <main class="messages-shell">
        <aside class="messages-sidebar">
            <?php include("welcome.php"); ?>
        </aside>

        <section class="messages-main">
            <?php if($flashMessage !== ""){ ?>
            <div class="messages-flash"><?php echo $flashMessage; ?></div>
            <?php } ?>

            <section class="messages-hero">
                <div class="messages-hero__copy">
                    <span class="messages-kicker"><?php echo msg_esc($__RoleLabel); ?></span>
                    <h1>School Messages</h1>
                    <p>Send updates, review your posts, and follow the message board.</p>
                </div>
                <div class="messages-stats">
                    <article class="messages-stat">
                        <span>My Messages</span>
                        <strong><?php echo (int)$myMessageCount; ?></strong>
                    </article>
                    <article class="messages-stat">
                        <span>Visible Board Posts</span>
                        <strong><?php echo (int)$boardMessageCount; ?></strong>
                    </article>
                    <article class="messages-stat">
                        <span>Audience View</span>
                        <strong><?php echo msg_esc($__SystemType === '' ? 'General' : $__SystemType); ?></strong>
                    </article>
                </div>
            </section>

            <div class="messages-note">
                <i class="fa fa-info-circle"></i>
                <span><?php echo msg_esc($visibilityHint); ?></span>
            </div>

            <div class="messages-grid">
                <section class="messages-card messages-card--composer">
                    <div class="messages-card__header">
                        <div>
                            <span class="messages-card__eyebrow">Write Message</span>
                            <h2>Send a new message</h2>
                        </div>
                    </div>

                    <form method="post" class="messages-form">
                        <input type="hidden" id="userid" name="userid" value="<?php echo msg_esc($__CurrentUserId); ?>" readonly>

                        <label for="message">Message</label>
                        <textarea id="message" name="message" placeholder="Type your message here..." required></textarea>

                        <?php if(count($__TargetOptions) > 1){ ?>
                        <label for="message_target">Send To</label>
                        <select id="message_target" name="message_target">
                            <?php foreach($__TargetOptions as $__TargetKey => $__TargetMeta){ ?>
                            <option value="<?php echo msg_esc($__TargetKey); ?>"<?php echo ($__TargetKey === $__DefaultTarget ? " selected" : ""); ?>><?php echo msg_esc(msg_target_label($__TargetMeta['recipient_type'], $__TargetMeta['recipient_group'], $__TargetMeta['recipient_label'])); ?></option>
                            <?php } ?>
                        </select>
                        <div class="messages-target-preview" data-messages-target-preview>
                            Sending to: <strong><?php echo msg_esc(msg_target_label($__TargetOptions[$__DefaultTarget]['recipient_type'], $__TargetOptions[$__DefaultTarget]['recipient_group'], $__TargetOptions[$__DefaultTarget]['recipient_label'])); ?></strong>
                        </div>
                        <?php } else { ?>
                        <div class="messages-helper">
                            Your message will go to <?php echo msg_esc(msg_target_label($__TargetOptions[$__DefaultTarget]['recipient_type'], $__TargetOptions[$__DefaultTarget]['recipient_group'], $__TargetOptions[$__DefaultTarget]['recipient_label'])); ?>.
                        </div>
                        <?php } ?>

                        <div class="messages-form__actions">
                            <span>Signed in as <?php echo msg_esc($__UserDisplayName); ?></span>
                            <button class="messages-primary-btn" type="submit" name="send_message"><i class="fa fa-send"></i> Send Message</button>
                        </div>
                    </form>
                </section>

                <section class="messages-card messages-card--mine">
                    <div class="messages-card__header">
                        <div>
                            <span class="messages-card__eyebrow">My Posts</span>
                            <h2>Your recent messages</h2>
                        </div>
                        <span class="messages-count"><?php echo (int)$myMessageCount; ?></span>
                    </div>

                    <div class="messages-feed">
                        <?php if(count($myMessages) > 0){ ?>
                            <?php foreach($myMessages as $message){ ?>
                            <article class="messages-item messages-item--mine">
                                <div class="messages-item__meta">
                                    <span class="<?php echo msg_esc(msg_target_badge_class(isset($message['recipient_type']) ? $message['recipient_type'] : 'group', isset($message['recipient_group']) ? $message['recipient_group'] : 'all')); ?>">
                                        <?php echo msg_esc(msg_target_badge_label(isset($message['recipient_type']) ? $message['recipient_type'] : 'group', isset($message['recipient_group']) ? $message['recipient_group'] : 'all', isset($message['recipient_label']) ? $message['recipient_label'] : '')); ?>
                                    </span>
                                    <span class="messages-target-label">To: <?php echo msg_esc(msg_target_label(isset($message['recipient_type']) ? $message['recipient_type'] : 'group', isset($message['recipient_group']) ? $message['recipient_group'] : 'all', isset($message['recipient_label']) ? $message['recipient_label'] : '')); ?></span>
                                    <span class="messages-time"><?php echo msg_esc(msg_time($message['datetimeentry'])); ?></span>
                                </div>
                                <p><?php echo nl2br(msg_esc($message['messages'])); ?></p>
                                <form method="post" class="messages-delete-form">
                                    <input type="hidden" name="messageid" value="<?php echo msg_esc($message['messageid']); ?>">
                                    <button type="submit" name="delete_message" class="messages-delete-btn" onclick="return confirm('Delete this message?');"><i class="fa fa-trash"></i> Delete</button>
                                </form>
                            </article>
                            <?php } ?>
                        <?php } else { ?>
                        <div class="messages-empty">
                            <h3>No messages yet</h3>
                            <p>Your sent messages will appear here after you post the first one.</p>
                        </div>
                        <?php } ?>
                    </div>
                </section>
            </div>

            <section class="messages-card messages-card--board">
                <div class="messages-card__header">
                    <div>
                        <span class="messages-card__eyebrow">Shared Board</span>
                        <h2>Latest visible messages</h2>
                    </div>
                    <span class="messages-count"><?php echo (int)$boardMessageCount; ?></span>
                </div>

                <div class="messages-feed messages-feed--board">
                    <?php if(count($boardMessages) > 0){ ?>
                        <?php foreach($boardMessages as $message){ ?>
                        <?php
                        $__SenderName = trim($message['firstname']." ".$message['othernames']." ".$message['surname']);
                        $__IsMine = ((string)$message['sentby'] === $__CurrentUserId);
                        ?>
                        <article class="messages-item">
                            <div class="messages-item__meta">
                                <div class="messages-item__who">
                                    <span class="<?php echo msg_esc(msg_target_badge_class(isset($message['recipient_type']) ? $message['recipient_type'] : 'group', isset($message['recipient_group']) ? $message['recipient_group'] : 'all')); ?>">
                                        <?php echo msg_esc(msg_target_badge_label(isset($message['recipient_type']) ? $message['recipient_type'] : 'group', isset($message['recipient_group']) ? $message['recipient_group'] : 'all', isset($message['recipient_label']) ? $message['recipient_label'] : '')); ?>
                                    </span>
                                    <span class="messages-target-label"><?php echo msg_esc(msg_target_label(isset($message['recipient_type']) ? $message['recipient_type'] : 'group', isset($message['recipient_group']) ? $message['recipient_group'] : 'all', isset($message['recipient_label']) ? $message['recipient_label'] : '')); ?></span>
                                    <strong><?php echo msg_esc($__SenderName !== '' ? $__SenderName : $message['sentby']); ?></strong>
                                    <span class="messages-role"><?php echo msg_esc($message['systemtype']); ?></span>
                                    <?php if($__IsMine){ ?><span class="messages-you">You</span><?php } ?>
                                </div>
                                <span class="messages-time"><?php echo msg_esc(msg_time($message['datetimeentry'])); ?></span>
                            </div>
                            <p><?php echo nl2br(msg_esc($message['messages'])); ?></p>
                            <?php if($__IsMine || $__CanManageAllMessages){ ?>
                            <form method="post" class="messages-delete-form">
                                <input type="hidden" name="messageid" value="<?php echo msg_esc($message['messageid']); ?>">
                                <button type="submit" name="delete_message" class="messages-delete-btn" onclick="return confirm('Delete this message?');"><i class="fa fa-trash"></i> Delete</button>
                            </form>
                            <?php } ?>
                        </article>
                        <?php } ?>
                    <?php } else { ?>
                    <div class="messages-empty">
                        <h3>No board messages yet</h3>
                        <p>When new messages are posted to your visible audience, they will show here.</p>
                    </div>
                    <?php } ?>
                </div>
            </section>
        </section>
    </main>
    <script>
    (function(){
        var select = document.getElementById('message_target');
        var preview = document.querySelector('[data-messages-target-preview]');
        if(!select || !preview){
            return;
        }
        var strong = preview.querySelector('strong');
        var syncPreview = function(){
            var text = '';
            if(select.selectedIndex >= 0 && select.options[select.selectedIndex]){
                text = select.options[select.selectedIndex].text;
            }
            if(strong){
                strong.textContent = text;
            }else{
                preview.textContent = text === '' ? '' : 'Sending to: ' + text;
            }
        };
        select.addEventListener('change', syncPreview);
        syncPreview();
    })();
    </script>
</body>
</html>
