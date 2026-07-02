<?php
session_start();
$_SESSION['Message'] = isset($_SESSION['Message']) ? $_SESSION['Message'] : '';
include("dbstring.php");
include("check-login.php");
include("class-teacher-utils.php");
include("house-master-utils.php");
include("online-class-utils.php");
ensure_class_teacher_table($con);
ensure_house_tables($con);
ensure_online_class_tables($con);

if(!(online_class_is_teacher() || online_class_is_student())){
    header("location:".online_class_landing_page());
    exit();
}

if(!function_exists('ocl_redirect')){
function ocl_redirect($query = ''){
    header("location:online-class.php".$query);
    exit();
}
}

if(!function_exists('ocl_flash')){
function ocl_flash($tone, $message){
    $tone = trim((string)$tone);
    $allowed = array('success', 'error', 'warning', 'info');
    if(!in_array($tone, $allowed, true)){
        $tone = 'info';
    }
    $icon = 'fa-info-circle';
    if($tone === 'success'){
        $icon = 'fa-check-circle';
    }elseif($tone === 'error'){
        $icon = 'fa-exclamation-circle';
    }elseif($tone === 'warning'){
        $icon = 'fa-exclamation-triangle';
    }
    return "<div class='online-class-flash online-class-flash--".$tone."'><span class='online-class-flash__icon'><i class='fa ".$icon."'></i></span><div class='online-class-flash__body'>".$message."</div></div>";
}
}

if(!function_exists('ocl_valid_date')){
function ocl_valid_date($value){
    $value = trim((string)$value);
    if($value === ''){
        return false;
    }
    $parts = date_parse($value);
    return isset($parts['error_count'], $parts['warning_count']) &&
        (int)$parts['error_count'] === 0 &&
        (int)$parts['warning_count'] === 0 &&
        checkdate((int)$parts['month'], (int)$parts['day'], (int)$parts['year']);
}
}

if(!function_exists('ocl_valid_time')){
function ocl_valid_time($value){
    $value = trim((string)$value);
    return (bool)preg_match('/^\d{2}:\d{2}$/', $value);
}
}

if(!function_exists('ocl_scope_map')){
function ocl_scope_map($scopeRows){
    $map = array();
    foreach($scopeRows as $scopeRow){
        if(!isset($scopeRow['scope_key'])){
            continue;
        }
        $map[(string)$scopeRow['scope_key']] = $scopeRow;
    }
    return $map;
}
}

if(!function_exists('ocl_partition_sessions')){
function ocl_partition_sessions($sessionRows){
    $groups = array(
        'live' => array(),
        'upcoming' => array(),
        'completed' => array(),
        'cancelled' => array()
    );
    foreach($sessionRows as $row){
        $state = online_class_session_state($row);
        $row['state_meta'] = $state;
        if($state['code'] === 'live'){
            $groups['live'][] = $row;
        }elseif($state['code'] === 'upcoming' || $state['code'] === 'scheduled'){
            $groups['upcoming'][] = $row;
        }elseif($state['code'] === 'cancelled'){
            $groups['cancelled'][] = $row;
        }else{
            $groups['completed'][] = $row;
        }
    }
    return $groups;
}
}

if(!function_exists('ocl_session_actions_html')){
function ocl_session_actions_html($row, $viewerMode){
    $viewerMode = trim((string)$viewerMode);
    $state = isset($row['state_meta']) && is_array($row['state_meta']) ? $row['state_meta'] : online_class_session_state($row);
    $link = isset($row['meetinglink']) ? trim((string)$row['meetinglink']) : '';
    $sessionId = isset($row['sessionid']) ? trim((string)$row['sessionid']) : '';

    $parts = array();
    if($link !== '' && $state['joinable']){
        $label = ($viewerMode === 'student') ? 'Join Class' : 'Open Link';
        $parts[] = "<a class='online-class-btn online-class-btn--primary' href='".online_class_esc($link)."' target='_blank' rel='noopener'><i class='fa fa-video-camera'></i> ".online_class_esc($label)."</a>";
    }elseif($link !== '' && $viewerMode === 'teacher'){
        $parts[] = "<a class='online-class-btn online-class-btn--secondary' href='".online_class_esc($link)."' target='_blank' rel='noopener'><i class='fa fa-external-link'></i> Open Link</a>";
    }

    if($viewerMode === 'teacher' && $sessionId !== ''){
        $parts[] = "<a class='online-class-btn online-class-btn--secondary' href='online-class.php?edit=".rawurlencode($sessionId)."'><i class='fa fa-pencil'></i> Edit</a>";
        $targetStatus = strtolower(trim((string)(isset($row['status']) ? $row['status'] : 'active'))) === 'cancelled' ? 'active' : 'cancelled';
        $targetLabel = $targetStatus === 'active' ? 'Reopen' : 'Cancel';
        $parts[] = "<form method='post' action='online-class.php' class='online-class-inline-form'>"
            ."<input type='hidden' name='sessionid' value='".online_class_esc($sessionId)."'>"
            ."<input type='hidden' name='target_status' value='".online_class_esc($targetStatus)."'>"
            ."<button class='online-class-btn online-class-btn--ghost' type='submit' name='toggle_online_class_status'><i class='fa fa-refresh'></i> ".online_class_esc($targetLabel)."</button>"
            ."</form>";
    }

    if(empty($parts)){
        return '';
    }

    return "<div class='online-class-card__actions'>".implode('', $parts)."</div>";
}
}

if(!function_exists('ocl_session_card_html')){
function ocl_session_card_html($row, $viewerMode){
    $state = isset($row['state_meta']) && is_array($row['state_meta']) ? $row['state_meta'] : online_class_session_state($row);
    $teacherName = online_class_full_name($row);
    $className = isset($row['class_name']) ? trim((string)$row['class_name']) : '';
    $batchName = isset($row['batch']) ? trim((string)$row['batch']) : '';
    $termName = isset($row['termname']) ? trim((string)$row['termname']) : '';
    $topic = isset($row['topic']) ? trim((string)$row['topic']) : '';
    $instructions = isset($row['instructions']) ? trim((string)$row['instructions']) : '';
    $subjectLabel = online_class_subject_label($row);

    $html = "<article class='online-class-card'>";
    $html .= "<div class='online-class-card__top'><span class='".online_class_esc($state['badge_class'])."'>".online_class_esc($state['label'])."</span>";
    $html .= "<span class='online-class-pill'>".online_class_esc($className)."</span></div>";
    $html .= "<h3>".online_class_esc($topic === '' ? 'Online Class Session' : $topic)."</h3>";
    $html .= "<p class='online-class-card__subject'>".online_class_esc($subjectLabel)."</p>";
    $html .= "<div class='online-class-card__meta'>";
    $html .= "<span><i class='fa fa-calendar'></i> ".online_class_esc(online_class_schedule_label($row))."</span>";
    $html .= "<span><i class='fa fa-folder-open-o'></i> ".online_class_esc($batchName.' · '.online_class_term_label($termName))."</span>";
    if($teacherName !== ''){
        $html .= "<span><i class='fa fa-user'></i> ".online_class_esc($teacherName)."</span>";
    }
    $html .= "</div>";
    if($instructions !== ''){
        $html .= "<div class='online-class-card__note'>".nl2br(online_class_esc($instructions))."</div>";
    }
    $html .= ocl_session_actions_html($row, $viewerMode);
    $html .= "</article>";
    return $html;
}
}

$currentUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$isTeacherView = online_class_is_teacher();
$isStudentView = online_class_is_student();

if($isTeacherView){
    $teacherScopes = online_class_teacher_scopes($con, $currentUserId);
    $teacherScopeMap = ocl_scope_map($teacherScopes);

    if(isset($_POST['save_online_class'])){
        $sessionId = isset($_POST['sessionid']) ? trim((string)$_POST['sessionid']) : '';
        $scopeKey = isset($_POST['scope_key']) ? trim((string)$_POST['scope_key']) : '';
        $subjectLine = isset($_POST['subjectline']) ? trim((string)$_POST['subjectline']) : '';
        $topic = isset($_POST['topic']) ? trim((string)$_POST['topic']) : '';
        $meetingLink = isset($_POST['meetinglink']) ? trim((string)$_POST['meetinglink']) : '';
        $sessionDate = isset($_POST['sessiondate']) ? trim((string)$_POST['sessiondate']) : '';
        $startTime = isset($_POST['starttime']) ? trim((string)$_POST['starttime']) : '';
        $endTime = isset($_POST['endtime']) ? trim((string)$_POST['endtime']) : '';
        $instructions = isset($_POST['instructions']) ? trim((string)$_POST['instructions']) : '';

        if($scopeKey === '' || !isset($teacherScopeMap[$scopeKey])){
            $_SESSION['Message'] = ocl_flash('error', 'Select one of your assigned class scopes before saving the session.');
            ocl_redirect();
        }
        if($topic === ''){
            $_SESSION['Message'] = ocl_flash('error', 'Enter the class topic or lesson title.');
            ocl_redirect();
        }
        if($meetingLink === '' || !preg_match('/^https?:\/\//i', $meetingLink)){
            $_SESSION['Message'] = ocl_flash('error', 'Paste a full meeting link starting with http:// or https://.');
            ocl_redirect();
        }
        if(!ocl_valid_date($sessionDate)){
            $_SESSION['Message'] = ocl_flash('error', 'Choose a valid class date.');
            ocl_redirect();
        }
        if(!ocl_valid_time($startTime)){
            $_SESSION['Message'] = ocl_flash('error', 'Choose a valid start time.');
            ocl_redirect();
        }
        if($endTime !== '' && !ocl_valid_time($endTime)){
            $_SESSION['Message'] = ocl_flash('error', 'Choose a valid end time or leave it blank.');
            ocl_redirect();
        }
        if($endTime !== '' && strtotime($sessionDate.' '.$endTime) <= strtotime($sessionDate.' '.$startTime)){
            $_SESSION['Message'] = ocl_flash('error', 'End time should be later than the start time.');
            ocl_redirect();
        }

        $scopeParts = online_class_parse_scope_key($scopeKey);
        $classIdEsc = mysqli_real_escape_string($con, $scopeParts['classid']);
        $batchIdEsc = mysqli_real_escape_string($con, $scopeParts['batchid']);
        $termNameEsc = mysqli_real_escape_string($con, $scopeParts['termname']);
        $teacherIdEsc = mysqli_real_escape_string($con, $currentUserId);
        $subjectEsc = mysqli_real_escape_string($con, substr($subjectLine, 0, 120));
        $topicEsc = mysqli_real_escape_string($con, substr($topic, 0, 150));
        $meetingLinkEsc = mysqli_real_escape_string($con, substr($meetingLink, 0, 255));
        $sessionDateEsc = mysqli_real_escape_string($con, $sessionDate);
        $startTimeEsc = mysqli_real_escape_string($con, $startTime);
        $recordedByEsc = mysqli_real_escape_string($con, $currentUserId);
        $instructionsEsc = mysqli_real_escape_string($con, $instructions);
        $subjectSql = $subjectLine !== '' ? "'$subjectEsc'" : "NULL";
        $instructionsSql = $instructions !== '' ? "'$instructionsEsc'" : "NULL";
        $endTimeSql = $endTime !== '' ? "'".mysqli_real_escape_string($con, $endTime)."'" : "NULL";

        if($sessionId !== ''){
            $sessionRow = online_class_fetch_session_row($con, $sessionId);
            if(!$sessionRow || trim((string)$sessionRow['teacherid']) !== $currentUserId){
                $_SESSION['Message'] = ocl_flash('error', 'That class session could not be updated.');
                ocl_redirect();
            }
            $sessionIdEsc = mysqli_real_escape_string($con, $sessionId);
            $sql = mysqli_query($con, "UPDATE tblonlineclasssession
                SET classid='$classIdEsc',
                    batchid='$batchIdEsc',
                    termname='$termNameEsc',
                    subjectline=$subjectSql,
                    topic='$topicEsc',
                    meetinglink='$meetingLinkEsc',
                    sessiondate='$sessionDateEsc',
                    starttime='$startTimeEsc',
                    endtime=$endTimeSql,
                    instructions=$instructionsSql,
                    updatedat=NOW(),
                    recordedby='$recordedByEsc'
                WHERE sessionid='$sessionIdEsc'
                  AND teacherid='$teacherIdEsc'");
            $_SESSION['Message'] = $sql
                ? ocl_flash('success', 'Online class session updated.')
                : ocl_flash('error', 'The online class session could not be updated.');
            ocl_redirect();
        }

        include("code.php");
        $newSessionId = isset($code) ? trim((string)$code) : '';
        if($newSessionId === ''){
            $newSessionId = md5($currentUserId.microtime(true));
        }
        $newSessionIdEsc = mysqli_real_escape_string($con, $newSessionId);
        $sql = mysqli_query($con, "INSERT INTO tblonlineclasssession(
                sessionid, teacherid, classid, batchid, termname, subjectline, topic, meetinglink,
                sessiondate, starttime, endtime, instructions, status, datetimeentry, updatedat, recordedby
            ) VALUES(
                '$newSessionIdEsc', '$teacherIdEsc', '$classIdEsc', '$batchIdEsc', '$termNameEsc', $subjectSql, '$topicEsc', '$meetingLinkEsc',
                '$sessionDateEsc', '$startTimeEsc', $endTimeSql, $instructionsSql, 'active', NOW(), NOW(), '$recordedByEsc'
            )");
        $_SESSION['Message'] = $sql
            ? ocl_flash('success', 'Online class session created.')
            : ocl_flash('error', 'The online class session could not be saved.');
        ocl_redirect();
    }

    if(isset($_POST['toggle_online_class_status'])){
        $sessionId = isset($_POST['sessionid']) ? trim((string)$_POST['sessionid']) : '';
        $targetStatus = strtolower(trim((string)(isset($_POST['target_status']) ? $_POST['target_status'] : '')));
        if(!in_array($targetStatus, array('active', 'cancelled'), true)){
            $_SESSION['Message'] = ocl_flash('error', 'That status change is not allowed.');
            ocl_redirect();
        }
        $sessionRow = online_class_fetch_session_row($con, $sessionId);
        if(!$sessionRow || trim((string)$sessionRow['teacherid']) !== $currentUserId){
            $_SESSION['Message'] = ocl_flash('error', 'That class session could not be updated.');
            ocl_redirect();
        }
        $sessionIdEsc = mysqli_real_escape_string($con, $sessionId);
        $targetStatusEsc = mysqli_real_escape_string($con, $targetStatus);
        $recordedByEsc = mysqli_real_escape_string($con, $currentUserId);
        $sql = mysqli_query($con, "UPDATE tblonlineclasssession
            SET status='$targetStatusEsc', updatedat=NOW(), recordedby='$recordedByEsc'
            WHERE sessionid='$sessionIdEsc'
              AND teacherid='".mysqli_real_escape_string($con, $currentUserId)."'");
        $_SESSION['Message'] = $sql
            ? ocl_flash('success', $targetStatus === 'active' ? 'Class session reopened.' : 'Class session cancelled.')
            : ocl_flash('error', 'The class session status could not be changed.');
        ocl_redirect();
    }
}

$messageHtml = $_SESSION['Message'];
$_SESSION['Message'] = '';

$editSessionRow = null;
$editScopeKey = '';
if($isTeacherView && isset($_GET['edit'])){
    $candidate = online_class_fetch_session_row($con, $_GET['edit']);
    if($candidate && trim((string)$candidate['teacherid']) === $currentUserId){
        $editSessionRow = $candidate;
        $editScopeKey = online_class_scope_key($candidate['classid'], $candidate['batchid'], $candidate['termname']);
    }
}

$sessionRows = $isTeacherView
    ? online_class_teacher_sessions($con, $currentUserId)
    : online_class_student_sessions($con, $currentUserId);
$sessionGroups = ocl_partition_sessions($sessionRows);

$pageMode = $isTeacherView ? 'teacher' : 'student';
$pageTitle = $isTeacherView ? 'Online Class' : 'Join Online Class';
$heroTitle = $isTeacherView ? 'Schedule Online Classes' : 'Join Your Online Classes';
$heroSummary = $isTeacherView
    ? 'Create meeting sessions for your class, paste the meeting link, and let the right students join from their dashboard.'
    : 'Your teachers can post live class links here. Open the session when it is live or when your class is about to start.';
$statCountA = $isTeacherView ? count(online_class_teacher_scopes($con, $currentUserId)) : count(online_class_student_scopes($con, $currentUserId));
$statLabelA = $isTeacherView ? 'Assigned Scopes' : 'My Classes';
$statCountB = count($sessionGroups['live']);
$statLabelB = 'Live Now';
$statCountC = count($sessionGroups['upcoming']);
$statLabelC = 'Upcoming';
$statCountD = $isTeacherView ? count($sessionRows) : (count($sessionGroups['live']) + count($sessionGroups['upcoming']) + count($sessionGroups['completed']));
$statLabelD = $isTeacherView ? 'All Sessions' : 'Recent Sessions';
$teacherScopes = $isTeacherView ? online_class_teacher_scopes($con, $currentUserId) : array();
$teacherScopeMap = $isTeacherView ? ocl_scope_map($teacherScopes) : array();
$studentScopes = $isStudentView ? online_class_student_scopes($con, $currentUserId) : array();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/online-class.css">
</head>
<body class="online-class-page online-class-page--<?php echo online_class_esc($pageMode); ?>">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <main class="online-class-shell">
        <section class="online-class-hero">
            <div class="online-class-hero__copy">
                <span class="online-class-kicker"><i class="fa fa-video-camera"></i> <?php echo online_class_esc($pageTitle); ?></span>
                <h1><?php echo online_class_esc($heroTitle); ?></h1>
                <p><?php echo online_class_esc($heroSummary); ?></p>
                <?php if($isTeacherView && !empty($teacherScopes)){ ?>
                <div class="online-class-chip-row">
                    <?php foreach(array_slice($teacherScopes, 0, 4) as $scopeRow){ ?>
                    <span class="online-class-chip"><?php echo online_class_esc($scopeRow['class_name'].' · '.trim((string)$scopeRow['batch']).' · '.online_class_term_label($scopeRow['termname'])); ?></span>
                    <?php } ?>
                </div>
                <?php }elseif($isStudentView && !empty($studentScopes)){ ?>
                <div class="online-class-chip-row">
                    <?php foreach(array_slice($studentScopes, 0, 4) as $scopeRow){ ?>
                    <span class="online-class-chip"><?php echo online_class_esc($scopeRow['class_name'].' · '.trim((string)$scopeRow['batch'])); ?></span>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <div class="online-class-stats">
                <article class="online-class-stat">
                    <span><?php echo online_class_esc($statLabelA); ?></span>
                    <strong><?php echo number_format((int)$statCountA); ?></strong>
                </article>
                <article class="online-class-stat online-class-stat--live">
                    <span><?php echo online_class_esc($statLabelB); ?></span>
                    <strong><?php echo number_format((int)$statCountB); ?></strong>
                </article>
                <article class="online-class-stat">
                    <span><?php echo online_class_esc($statLabelC); ?></span>
                    <strong><?php echo number_format((int)$statCountC); ?></strong>
                </article>
                <article class="online-class-stat">
                    <span><?php echo online_class_esc($statLabelD); ?></span>
                    <strong><?php echo number_format((int)$statCountD); ?></strong>
                </article>
            </div>
        </section>

        <?php if($messageHtml !== ''){ ?>
        <div class="online-class-message-stack"><?php echo $messageHtml; ?></div>
        <?php } ?>

        <?php if($isTeacherView){ ?>
        <div class="online-class-layout">
            <aside class="online-class-sidebar">
                <section class="online-class-surface">
                    <div class="online-class-panel-head">
                        <div>
                            <span class="online-class-panel-kicker">Session Setup</span>
                            <h2><?php echo $editSessionRow ? 'Update Class Session' : 'Schedule A Class'; ?></h2>
                            <p>Select your class scope, add the meeting link, and save the session for students to join.</p>
                        </div>
                        <?php if($editSessionRow){ ?>
                        <a class="online-class-btn online-class-btn--secondary" href="online-class.php"><i class="fa fa-times"></i> Clear</a>
                        <?php } ?>
                    </div>

                    <?php if(empty($teacherScopes)){ ?>
                    <div class="online-class-empty-state online-class-empty-state--inline">
                        <h3>No teaching scope found</h3>
                        <p>Your subject or class assignment needs to be active before you can schedule an online class.</p>
                    </div>
                    <?php }else{ ?>
                    <form method="post" action="online-class.php<?php echo $editSessionRow ? '?edit='.rawurlencode((string)$editSessionRow['sessionid']) : ''; ?>" class="online-class-form">
                        <input type="hidden" name="sessionid" value="<?php echo online_class_esc($editSessionRow ? (string)$editSessionRow['sessionid'] : ''); ?>">

                        <label class="online-class-field">
                            <span>Class Scope</span>
                            <select name="scope_key" required>
                                <option value="">Select Class Scope</option>
                                <?php foreach($teacherScopes as $scopeRow){ ?>
                                <?php
                                $scopeKey = (string)$scopeRow['scope_key'];
                                $selected = $editScopeKey !== '' && $editScopeKey === $scopeKey;
                                $scopeLabel = trim((string)$scopeRow['class_name']).' · '.trim((string)$scopeRow['batch']).' · '.online_class_term_label($scopeRow['termname']);
                                $scopeHint = trim((string)(isset($scopeRow['subject_summary']) ? $scopeRow['subject_summary'] : ''));
                                ?>
                                <option value="<?php echo online_class_esc($scopeKey); ?>"<?php echo $selected ? ' selected' : ''; ?>>
                                    <?php echo online_class_esc($scopeLabel.($scopeHint !== '' ? ' · '.$scopeHint : '')); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </label>

                        <div class="online-class-form-grid">
                            <label class="online-class-field">
                                <span>Subject</span>
                                <input type="text" name="subjectline" maxlength="120" value="<?php echo online_class_esc($editSessionRow ? (string)$editSessionRow['subjectline'] : ''); ?>" placeholder="Optional subject name">
                            </label>
                            <label class="online-class-field">
                                <span>Topic</span>
                                <input type="text" name="topic" maxlength="150" value="<?php echo online_class_esc($editSessionRow ? (string)$editSessionRow['topic'] : ''); ?>" placeholder="Lesson topic or class title" required>
                            </label>
                        </div>

                        <label class="online-class-field">
                            <span>Meeting Link</span>
                            <input type="url" name="meetinglink" maxlength="255" value="<?php echo online_class_esc($editSessionRow ? (string)$editSessionRow['meetinglink'] : ''); ?>" placeholder="https://meet.google.com/..." required>
                        </label>

                        <div class="online-class-form-grid online-class-form-grid--schedule">
                            <label class="online-class-field">
                                <span>Date</span>
                                <input type="date" name="sessiondate" value="<?php echo online_class_esc($editSessionRow ? (string)$editSessionRow['sessiondate'] : date('Y-m-d')); ?>" required>
                            </label>
                            <label class="online-class-field">
                                <span>Start Time</span>
                                <input type="time" name="starttime" value="<?php echo online_class_esc($editSessionRow ? substr((string)$editSessionRow['starttime'], 0, 5) : date('H:00')); ?>" required>
                            </label>
                            <label class="online-class-field">
                                <span>End Time</span>
                                <input type="time" name="endtime" value="<?php echo online_class_esc($editSessionRow && trim((string)$editSessionRow['endtime']) !== '' ? substr((string)$editSessionRow['endtime'], 0, 5) : ''); ?>">
                            </label>
                        </div>

                        <label class="online-class-field">
                            <span>Class Note</span>
                            <textarea name="instructions" rows="4" placeholder="Add a short note for students, such as what to bring or when to join."><?php echo online_class_esc($editSessionRow ? (string)$editSessionRow['instructions'] : ''); ?></textarea>
                        </label>

                        <div class="online-class-inline-note">
                            <i class="fa fa-info-circle"></i>
                            <span>Students only see active sessions linked to their class. Cancel a session when you no longer want it to appear.</span>
                        </div>

                        <div class="online-class-form-actions">
                            <button class="online-class-btn online-class-btn--primary" type="submit" name="save_online_class"><i class="fa fa-save"></i> <?php echo $editSessionRow ? 'Update Session' : 'Save Session'; ?></button>
                            <?php if($editSessionRow){ ?>
                            <a class="online-class-btn online-class-btn--secondary" href="online-class.php"><i class="fa fa-arrow-left"></i> Back</a>
                            <?php } ?>
                        </div>
                    </form>
                    <?php } ?>
                </section>
            </aside>

            <section class="online-class-main">
                <section class="online-class-surface">
                    <div class="online-class-panel-head">
                        <div>
                            <span class="online-class-panel-kicker">Class Sessions</span>
                            <h2>Your Saved Sessions</h2>
                            <p>Live and upcoming sessions appear to students as long as the session stays active.</p>
                        </div>
                    </div>

                    <?php if(empty($sessionRows)){ ?>
                    <div class="online-class-empty-state">
                        <h3>No sessions yet</h3>
                        <p>Your saved online classes will appear here after you schedule the first session.</p>
                    </div>
                    <?php }else{ ?>
                    <?php if(!empty($sessionGroups['live'])){ ?>
                    <div class="online-class-section-block">
                        <div class="online-class-section-heading"><h3>Live Now</h3><span><?php echo number_format((int)count($sessionGroups['live'])); ?></span></div>
                        <div class="online-class-card-grid">
                            <?php foreach($sessionGroups['live'] as $sessionRow){ echo ocl_session_card_html($sessionRow, 'teacher'); } ?>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if(!empty($sessionGroups['upcoming'])){ ?>
                    <div class="online-class-section-block">
                        <div class="online-class-section-heading"><h3>Upcoming</h3><span><?php echo number_format((int)count($sessionGroups['upcoming'])); ?></span></div>
                        <div class="online-class-card-grid">
                            <?php foreach($sessionGroups['upcoming'] as $sessionRow){ echo ocl_session_card_html($sessionRow, 'teacher'); } ?>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if(!empty($sessionGroups['completed'])){ ?>
                    <div class="online-class-section-block">
                        <div class="online-class-section-heading"><h3>Completed</h3><span><?php echo number_format((int)count($sessionGroups['completed'])); ?></span></div>
                        <div class="online-class-card-grid">
                            <?php foreach($sessionGroups['completed'] as $sessionRow){ echo ocl_session_card_html($sessionRow, 'teacher'); } ?>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if(!empty($sessionGroups['cancelled'])){ ?>
                    <div class="online-class-section-block">
                        <div class="online-class-section-heading"><h3>Cancelled</h3><span><?php echo number_format((int)count($sessionGroups['cancelled'])); ?></span></div>
                        <div class="online-class-card-grid">
                            <?php foreach($sessionGroups['cancelled'] as $sessionRow){ echo ocl_session_card_html($sessionRow, 'teacher'); } ?>
                        </div>
                    </div>
                    <?php } ?>
                    <?php } ?>
                </section>
            </section>
        </div>
        <?php }else{ ?>
        <section class="online-class-surface">
            <div class="online-class-panel-head">
                <div>
                    <span class="online-class-panel-kicker">Available Sessions</span>
                    <h2>Class Links From Your Teachers</h2>
                    <p>Open the session when it goes live or a little before the class starts.</p>
                </div>
            </div>

            <?php if(empty($studentScopes)){ ?>
            <div class="online-class-empty-state">
                <h3>No active class found</h3>
                <p>Your current class is not available yet. Once your class record is active, your online sessions will show here.</p>
            </div>
            <?php } elseif(empty($sessionRows)){ ?>
            <div class="online-class-empty-state">
                <h3>No online class posted yet</h3>
                <p>Check again later when your teacher shares the next session link.</p>
            </div>
            <?php } else { ?>
            <?php if(!empty($sessionGroups['live'])){ ?>
            <div class="online-class-section-block">
                <div class="online-class-section-heading"><h3>Live Now</h3><span><?php echo number_format((int)count($sessionGroups['live'])); ?></span></div>
                <div class="online-class-card-grid">
                    <?php foreach($sessionGroups['live'] as $sessionRow){ echo ocl_session_card_html($sessionRow, 'student'); } ?>
                </div>
            </div>
            <?php } ?>

            <?php if(!empty($sessionGroups['upcoming'])){ ?>
            <div class="online-class-section-block">
                <div class="online-class-section-heading"><h3>Upcoming</h3><span><?php echo number_format((int)count($sessionGroups['upcoming'])); ?></span></div>
                <div class="online-class-card-grid">
                    <?php foreach($sessionGroups['upcoming'] as $sessionRow){ echo ocl_session_card_html($sessionRow, 'student'); } ?>
                </div>
            </div>
            <?php } ?>

            <?php if(!empty($sessionGroups['completed'])){ ?>
            <div class="online-class-section-block">
                <div class="online-class-section-heading"><h3>Earlier Sessions</h3><span><?php echo number_format((int)count($sessionGroups['completed'])); ?></span></div>
                <div class="online-class-card-grid">
                    <?php foreach(array_slice($sessionGroups['completed'], 0, 6) as $sessionRow){ echo ocl_session_card_html($sessionRow, 'student'); } ?>
                </div>
            </div>
            <?php } ?>
            <?php } ?>
        </section>
        <?php } ?>
    </main>
</div>
</body>
</html>
