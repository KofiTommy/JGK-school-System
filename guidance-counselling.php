<?php
session_start();
$_SESSION['Message'] = isset($_SESSION['Message']) ? $_SESSION['Message'] : '';
include("check-login.php");
include("dbstring.php");
include("counselling-utils.php");
ensure_counselling_tables($con);
counselling_process_due_reminders($con);

if(!(counselling_is_student() || counselling_is_teacher())){
    header("location:".counselling_landing_page());
    exit();
}

if(!function_exists('gc_redirect')){
function gc_redirect($query = ''){
    header("location:guidance-counselling.php".$query);
    exit();
}
}

if(!function_exists('gc_flash_html')){
function gc_flash_html($tone, $message){
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
    return "<div class='gc-flash gc-flash--".$tone."'><span class='gc-flash__icon'><i class='fa ".$icon."'></i></span><div class='gc-flash__body'>".$message."</div></div>";
}
}

if(!function_exists('gc_category_label')){
function gc_category_label($value){
    $value = strtolower(trim((string)$value));
    $map = array(
        'academic' => 'Academic Support',
        'personal' => 'Personal Support',
        'welfare' => 'Welfare',
        'discipline' => 'Discipline',
        'bullying' => 'Bullying',
        'career' => 'Career Guidance',
        'health' => 'Health',
        'other' => 'Other'
    );
    return isset($map[$value]) ? $map[$value] : 'General';
}
}

if(!function_exists('gc_mode_label')){
function gc_mode_label($value){
    $value = strtolower(trim((string)$value));
    $map = array(
        'in_person' => 'In Person',
        'phone' => 'Phone',
        'online' => 'Online'
    );
    return isset($map[$value]) ? $map[$value] : 'Session';
}
}

if(!function_exists('gc_urgency_label')){
function gc_urgency_label($value){
    $value = strtolower(trim((string)$value));
    $map = array(
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High'
    );
    return isset($map[$value]) ? $map[$value] : 'Normal';
}
}

if(!function_exists('gc_subject_total')){
function gc_subject_total($value){
    if($value === null || $value === ''){
        return '-';
    }
    $number = (float)$value;
    if(abs($number - round($number)) < 0.0001){
        return (string)((int)round($number));
    }
    return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
}
}

if(!function_exists('gc_request_location_text')){
function gc_request_location_text($requestRow){
    $mode = trim((string)(isset($requestRow['sessionmode']) ? $requestRow['sessionmode'] : ''));
    if($mode === 'online'){
        $link = trim((string)(isset($requestRow['meetinglink']) ? $requestRow['meetinglink'] : ''));
        return $link !== '' ? $link : 'Link pending';
    }
    $venue = trim((string)(isset($requestRow['venue']) ? $requestRow['venue'] : ''));
    return $venue !== '' ? $venue : 'To be confirmed';
}
}

if(!function_exists('gc_request_schedule_text')){
function gc_request_schedule_text($requestRow, $prefix = 'scheduled'){
    $dateKey = $prefix === 'preferred' ? 'preferred_date' : 'scheduled_date';
    $timeKey = $prefix === 'preferred' ? 'preferred_time' : 'scheduled_time';
    $dateText = counselling_format_date(isset($requestRow[$dateKey]) ? $requestRow[$dateKey] : '');
    $timeText = counselling_format_time(isset($requestRow[$timeKey]) ? $requestRow[$timeKey] : '');
    if($dateText === 'Not set' && $timeText === 'Not set'){
        return 'Not set';
    }
    if($dateText === 'Not set'){
        return $timeText;
    }
    if($timeText === 'Not set'){
        return $dateText;
    }
    return $dateText.' · '.$timeText;
}
}

if(!function_exists('gc_case_link')){
function gc_case_link($requestId){
    return 'guidance-counselling.php?case='.rawurlencode((string)$requestId);
}
}

if(!function_exists('gc_append_case_message')){
function gc_append_case_message($con, $requestId, $senderId, $senderRole, $messageText){
    $requestId = trim((string)$requestId);
    $senderId = trim((string)$senderId);
    $senderRole = trim((string)$senderRole);
    $messageText = trim((string)$messageText);
    if(!$con || $requestId === '' || $senderId === '' || $senderRole === '' || $messageText === ''){
        return false;
    }
    include("code.php");
    $messageIdEsc = mysqli_real_escape_string($con, trim((string)$code));
    $requestIdEsc = mysqli_real_escape_string($con, $requestId);
    $senderIdEsc = mysqli_real_escape_string($con, $senderId);
    $senderRoleEsc = mysqli_real_escape_string($con, substr($senderRole, 0, 20));
    $messageEsc = mysqli_real_escape_string($con, $messageText);
    return mysqli_query($con, "INSERT INTO tblcounsellingmessage(
            messageid, requestid, senderid, senderrole, messagetext, datetimeentry, status
        ) VALUES(
            '$messageIdEsc', '$requestIdEsc', '$senderIdEsc', '$senderRoleEsc', '$messageEsc', NOW(), 'active'
        )") ? true : false;
}
}

if(!function_exists('gc_page_link')){
function gc_page_link($params = array()){
    $pairs = array();
    foreach($params as $key => $value){
        $key = trim((string)$key);
        $value = trim((string)$value);
        if($key === '' || $value === ''){
            continue;
        }
        $pairs[] = rawurlencode($key).'='.rawurlencode($value);
    }
    return 'guidance-counselling.php'.(!empty($pairs) ? '?'.implode('&', $pairs) : '');
}
}

if(!function_exists('gc_normalize_date')){
function gc_normalize_date($value, $fallback = ''){
    $value = trim((string)$value);
    if($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)){
        $time = strtotime($value);
        if($time){
            return date('Y-m-d', $time);
        }
    }
    $fallback = trim((string)$fallback);
    if($fallback !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fallback)){
        $time = strtotime($fallback);
        if($time){
            return date('Y-m-d', $time);
        }
    }
    return date('Y-m-d');
}
}

if(!function_exists('gc_timetable_slot_text')){
function gc_timetable_slot_text($requestRow){
    $startText = counselling_format_time(isset($requestRow['scheduled_time']) ? $requestRow['scheduled_time'] : '');
    $endText = counselling_format_time(isset($requestRow['scheduled_endtime']) ? $requestRow['scheduled_endtime'] : '');
    if($startText === 'Not set' && $endText === 'Not set'){
        return 'Time not set';
    }
    if($endText !== 'Not set' && $startText !== 'Not set'){
        return $startText.' - '.$endText;
    }
    return $startText !== 'Not set' ? $startText : $endText;
}
}

if(!function_exists('gc_group_schedule_rows')){
function gc_group_schedule_rows($rows){
    $groups = array();
    foreach($rows as $row){
        $dateKey = trim((string)(isset($row['scheduled_date']) ? $row['scheduled_date'] : ''));
        if($dateKey === ''){
            $dateKey = 'undated';
        }
        if(!isset($groups[$dateKey])){
            $groups[$dateKey] = array();
        }
        $groups[$dateKey][] = $row;
    }
    return $groups;
}
}

$currentUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$isStudentView = counselling_is_student();
$isTeacherView = counselling_is_teacher();
$viewerRole = $isStudentView ? 'student' : 'teacher';

if($isStudentView && isset($_POST['submit_counselling_request'])){
    $assignment = counselling_resolve_student_assignment($con, $currentUserId);
    $category = isset($_POST['category']) ? trim((string)$_POST['category']) : '';
    $sessionMode = isset($_POST['sessionmode']) ? trim((string)$_POST['sessionmode']) : '';
    $urgency = isset($_POST['urgency']) ? trim((string)$_POST['urgency']) : 'normal';
    $subjectLine = isset($_POST['subjectline']) ? trim((string)$_POST['subjectline']) : '';
    $preferredDate = isset($_POST['preferred_date']) ? trim((string)$_POST['preferred_date']) : '';
    $preferredTime = isset($_POST['preferred_time']) ? trim((string)$_POST['preferred_time']) : '';
    $concern = isset($_POST['concern']) ? trim((string)$_POST['concern']) : '';

    if(!$assignment){
        $_SESSION['Message'] = gc_flash_html('error', 'No dedicated counsellor has been assigned to you yet. Contact administration.');
        gc_redirect();
    }
    if($category === '' || $sessionMode === '' || $concern === ''){
        $_SESSION['Message'] = gc_flash_html('error', 'Choose a category, session type, and enter your request note before submitting.');
        gc_redirect();
    }
    if($preferredDate === '' || $preferredTime === ''){
        $_SESSION['Message'] = gc_flash_html('error', 'Select your preferred date and time.');
        gc_redirect();
    }

    include("code.php");
    $requestIdEsc = mysqli_real_escape_string($con, trim((string)$code));
    $studentIdEsc = mysqli_real_escape_string($con, $currentUserId);
    $counsellorIdEsc = mysqli_real_escape_string($con, trim((string)$assignment['counsellorid']));
    $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$assignment['assignmentid']));
    $categoryEsc = mysqli_real_escape_string($con, substr($category, 0, 40));
    $sessionModeEsc = mysqli_real_escape_string($con, substr($sessionMode, 0, 20));
    $urgencyEsc = mysqli_real_escape_string($con, substr($urgency, 0, 20));
    $subjectLineEsc = mysqli_real_escape_string($con, substr($subjectLine, 0, 120));
    $preferredDateEsc = mysqli_real_escape_string($con, $preferredDate);
    $preferredTimeEsc = mysqli_real_escape_string($con, $preferredTime);
    $concernEsc = mysqli_real_escape_string($con, $concern);
    $recordedByEsc = mysqli_real_escape_string($con, $currentUserId);
    $subjectLineSql = $subjectLine !== '' ? "'$subjectLineEsc'" : 'NULL';

    $sql = mysqli_query($con, "INSERT INTO tblcounsellingrequest(
            requestid, studentid, counsellorid, assignmentid, category, sessionmode, urgency, subjectline,
            concern, preferred_date, preferred_time, status, createdat, updatedat, recordedby
        ) VALUES(
            '$requestIdEsc', '$studentIdEsc', '$counsellorIdEsc', '$assignmentIdEsc', '$categoryEsc', '$sessionModeEsc', '$urgencyEsc', $subjectLineSql,
            '$concernEsc', '$preferredDateEsc', '$preferredTimeEsc', 'pending', NOW(), NOW(), '$recordedByEsc'
        )");

    $_SESSION['Message'] = $sql
        ? gc_flash_html('success', 'Your counselling request has been submitted.')
        : gc_flash_html('error', 'Your counselling request could not be submitted.');
    gc_redirect($sql ? '?case='.rawurlencode(trim((string)$code)) : '');
}

if($isTeacherView && isset($_POST['create_counselling_session'])){
    $studentId = isset($_POST['studentid']) ? trim((string)$_POST['studentid']) : '';
    $category = isset($_POST['category']) ? trim((string)$_POST['category']) : '';
    $sessionMode = isset($_POST['sessionmode']) ? trim((string)$_POST['sessionmode']) : '';
    $urgency = isset($_POST['urgency']) ? trim((string)$_POST['urgency']) : 'normal';
    $subjectLine = isset($_POST['subjectline']) ? trim((string)$_POST['subjectline']) : '';
    $scheduledDate = isset($_POST['scheduled_date']) ? trim((string)$_POST['scheduled_date']) : '';
    $scheduledTime = isset($_POST['scheduled_time']) ? trim((string)$_POST['scheduled_time']) : '';
    $scheduledEndTime = isset($_POST['scheduled_endtime']) ? trim((string)$_POST['scheduled_endtime']) : '';
    $meetingLink = isset($_POST['meetinglink']) ? trim((string)$_POST['meetinglink']) : '';
    $venue = isset($_POST['venue']) ? trim((string)$_POST['venue']) : '';
    $concern = isset($_POST['concern']) ? trim((string)$_POST['concern']) : '';

    if($studentId === '' || $category === '' || $sessionMode === '' || $scheduledDate === '' || $scheduledTime === ''){
        $_SESSION['Message'] = gc_flash_html('error', 'Select the student, category, session type, date, and time before saving the session.');
        gc_redirect();
    }
    if($concern === ''){
        $_SESSION['Message'] = gc_flash_html('error', 'Add a short note so the student knows why the session was arranged.');
        gc_redirect();
    }
    if($sessionMode === 'online' && $meetingLink === ''){
        $_SESSION['Message'] = gc_flash_html('error', 'Add the meeting link for an online counselling session.');
        gc_redirect();
    }
    if(!counselling_teacher_can_manage_student($con, $currentUserId, $studentId)){
        $_SESSION['Message'] = gc_flash_html('error', 'That student is not available under your counselling assignment.');
        gc_redirect();
    }

    $assignment = counselling_resolve_student_assignment($con, $studentId);
    if(!$assignment || trim((string)(isset($assignment['counsellorid']) ? $assignment['counsellorid'] : '')) !== $currentUserId){
        $_SESSION['Message'] = gc_flash_html('error', 'The selected student does not currently route to your counselling scope.');
        gc_redirect();
    }

    include("code.php");
    $requestId = trim((string)$code);
    $requestIdEsc = mysqli_real_escape_string($con, $requestId);
    $studentIdEsc = mysqli_real_escape_string($con, $studentId);
    $counsellorIdEsc = mysqli_real_escape_string($con, $currentUserId);
    $assignmentId = trim((string)(isset($assignment['assignmentid']) ? $assignment['assignmentid'] : ''));
    $assignmentIdSql = $assignmentId !== '' ? "'".mysqli_real_escape_string($con, $assignmentId)."'" : 'NULL';
    $categoryEsc = mysqli_real_escape_string($con, substr($category, 0, 40));
    $sessionModeEsc = mysqli_real_escape_string($con, substr($sessionMode, 0, 20));
    $urgencyEsc = mysqli_real_escape_string($con, substr($urgency, 0, 20));
    $subjectLineSql = $subjectLine !== '' ? "'".mysqli_real_escape_string($con, substr($subjectLine, 0, 120))."'" : 'NULL';
    $concernEsc = mysqli_real_escape_string($con, $concern);
    $scheduledDateEsc = mysqli_real_escape_string($con, $scheduledDate);
    $scheduledTimeEsc = mysqli_real_escape_string($con, $scheduledTime);
    $scheduledEndTimeSql = $scheduledEndTime !== '' ? "'".mysqli_real_escape_string($con, $scheduledEndTime)."'" : 'NULL';
    $meetingLinkSql = $meetingLink !== '' ? "'".mysqli_real_escape_string($con, substr($meetingLink, 0, 255))."'" : 'NULL';
    $venueSql = $venue !== '' ? "'".mysqli_real_escape_string($con, substr($venue, 0, 150))."'" : 'NULL';
    $recordedByEsc = mysqli_real_escape_string($con, $currentUserId);

    $sql = mysqli_query($con, "INSERT INTO tblcounsellingrequest(
            requestid, studentid, counsellorid, assignmentid, category, sessionmode, urgency, subjectline,
            concern, preferred_date, preferred_time, scheduled_date, scheduled_time, scheduled_endtime,
            meetinglink, venue, status, createdat, updatedat, recordedby
        ) VALUES(
            '$requestIdEsc', '$studentIdEsc', '$counsellorIdEsc', $assignmentIdSql, '$categoryEsc', '$sessionModeEsc', '$urgencyEsc', $subjectLineSql,
            '$concernEsc', '$scheduledDateEsc', '$scheduledTimeEsc', '$scheduledDateEsc', '$scheduledTimeEsc', $scheduledEndTimeSql,
            $meetingLinkSql, $venueSql, 'accepted', NOW(), NOW(), '$recordedByEsc'
        )");
    if($sql){
        $studentLabel = trim((string)(isset($assignment['student_scope']['class_name']) ? $assignment['student_scope']['class_name'] : ''));
        $autoMessage = "The counsellor arranged this session for ".counselling_format_date($scheduledDate)." at ".counselling_format_time($scheduledTime).".";
        if($studentLabel !== ''){
            $autoMessage .= " Please review the meeting details and respond if you need a different time.";
        }
        gc_append_case_message($con, $requestId, $currentUserId, 'teacher', $autoMessage);
    }

    $_SESSION['Message'] = $sql
        ? gc_flash_html('success', 'Counselling session created and shared with the student.')
        : gc_flash_html('error', 'The counselling session could not be created.');
    gc_redirect($sql ? '?case='.rawurlencode($requestId).'&schedule_date='.rawurlencode($scheduledDate) : '');
}

if($isStudentView && isset($_POST['student_manage_counselling_case'])){
    $requestId = isset($_POST['requestid']) ? trim((string)$_POST['requestid']) : '';
    $caseAction = strtolower(trim((string)(isset($_POST['student_case_action']) ? $_POST['student_case_action'] : '')));
    $requestedDate = isset($_POST['requested_date']) ? trim((string)$_POST['requested_date']) : '';
    $requestedTime = isset($_POST['requested_time']) ? trim((string)$_POST['requested_time']) : '';
    $requestedEndTime = isset($_POST['requested_endtime']) ? trim((string)$_POST['requested_endtime']) : '';
    $requestNote = isset($_POST['request_note']) ? trim((string)$_POST['request_note']) : '';
    $caseQuery = '?case='.rawurlencode($requestId);

    $requestRow = counselling_request_row($con, $requestId);
    if(!$requestRow || !counselling_user_can_view_request($con, $requestId, $currentUserId, 'student')){
        $_SESSION['Message'] = gc_flash_html('error', 'That counselling case could not be updated.');
        gc_redirect();
    }
    if(!counselling_request_active(isset($requestRow['status']) ? $requestRow['status'] : '')){
        $_SESSION['Message'] = gc_flash_html('warning', 'This case is already closed.');
        gc_redirect($caseQuery);
    }
    if(!in_array($caseAction, array('reschedule', 'cancel'), true)){
        $_SESSION['Message'] = gc_flash_html('error', 'Choose whether you want to reschedule or cancel the appointment.');
        gc_redirect($caseQuery);
    }
    if($caseAction === 'reschedule' && ($requestedDate === '' || $requestedTime === '')){
        $_SESSION['Message'] = gc_flash_html('error', 'Select the new date and time before sending the reschedule request.');
        gc_redirect($caseQuery);
    }

    $requestIdEsc = mysqli_real_escape_string($con, $requestId);
    $recordedByEsc = mysqli_real_escape_string($con, $currentUserId);
    $statusEsc = mysqli_real_escape_string($con, $caseAction === 'cancel' ? 'cancelled' : 'rescheduled');
    $requestedDateSql = $requestedDate !== '' ? "'".mysqli_real_escape_string($con, $requestedDate)."'" : 'NULL';
    $requestedTimeSql = $requestedTime !== '' ? "'".mysqli_real_escape_string($con, $requestedTime)."'" : 'NULL';
    $requestedEndTimeSql = $requestedEndTime !== '' ? "'".mysqli_real_escape_string($con, $requestedEndTime)."'" : 'NULL';

    if($caseAction === 'cancel'){
        $sql = mysqli_query($con, "UPDATE tblcounsellingrequest
            SET status='$statusEsc',
                updatedat=NOW(),
                recordedby='$recordedByEsc'
            WHERE requestid='$requestIdEsc'
              AND studentid='$recordedByEsc'");
        if($sql){
            $message = "The student cancelled this counselling appointment.";
            if($requestNote !== ''){
                $message .= " Note: ".$requestNote;
            }
            gc_append_case_message($con, $requestId, $currentUserId, 'student', $message);
        }
        $_SESSION['Message'] = $sql
            ? gc_flash_html('success', 'The counselling appointment has been cancelled.')
            : gc_flash_html('error', 'The appointment could not be cancelled.');
        gc_redirect($caseQuery);
    }

    $sql = mysqli_query($con, "UPDATE tblcounsellingrequest
        SET status='$statusEsc',
            preferred_date=$requestedDateSql,
            preferred_time=$requestedTimeSql,
            scheduled_date=$requestedDateSql,
            scheduled_time=$requestedTimeSql,
            scheduled_endtime=$requestedEndTimeSql,
            counsellorremindersentat=NULL,
            counsellorreminderstatus=NULL,
            counsellorreminderattemptat=NULL,
            updatedat=NOW(),
            recordedby='$recordedByEsc'
        WHERE requestid='$requestIdEsc'
          AND studentid='$recordedByEsc'");
    if($sql){
        $message = "The student requested a new counselling time for ".counselling_format_date($requestedDate)." at ".counselling_format_time($requestedTime).".";
        if($requestNote !== ''){
            $message .= " Note: ".$requestNote;
        }
        gc_append_case_message($con, $requestId, $currentUserId, 'student', $message);
    }

    $_SESSION['Message'] = $sql
        ? gc_flash_html('success', 'Your new counselling time has been shared with the counsellor.')
        : gc_flash_html('error', 'The appointment could not be rescheduled.');
    gc_redirect($sql ? '?case='.rawurlencode($requestId) : $caseQuery);
}

if(isset($_POST['send_counselling_message'])){
    $requestId = isset($_POST['requestid']) ? trim((string)$_POST['requestid']) : '';
    $messageText = isset($_POST['messagetext']) ? trim((string)$_POST['messagetext']) : '';
    $postedScheduleDate = isset($_POST['schedule_date']) ? gc_normalize_date($_POST['schedule_date']) : '';
    $caseQuery = '?case='.rawurlencode($requestId);
    if($isTeacherView && $postedScheduleDate !== ''){
        $caseQuery .= '&schedule_date='.rawurlencode($postedScheduleDate);
    }
    $requestRow = counselling_request_row($con, $requestId);
    if(!$requestRow || !counselling_user_can_view_request($con, $requestId, $currentUserId, $viewerRole)){
        $_SESSION['Message'] = gc_flash_html('error', 'That counselling case could not be opened.');
        gc_redirect();
    }
    if(!counselling_request_active(isset($requestRow['status']) ? $requestRow['status'] : '')){
        $_SESSION['Message'] = gc_flash_html('warning', 'This case is closed. Start a new request if you need more support.');
        gc_redirect($caseQuery);
    }
    if($messageText === ''){
        $_SESSION['Message'] = gc_flash_html('error', 'Type a message before sending.');
        gc_redirect($caseQuery);
    }

    include("code.php");
    $messageIdEsc = mysqli_real_escape_string($con, trim((string)$code));
    $requestIdEsc = mysqli_real_escape_string($con, $requestId);
    $senderIdEsc = mysqli_real_escape_string($con, $currentUserId);
    $senderRoleEsc = mysqli_real_escape_string($con, $viewerRole);
    $messageEsc = mysqli_real_escape_string($con, $messageText);
    $sql = mysqli_query($con, "INSERT INTO tblcounsellingmessage(
            messageid, requestid, senderid, senderrole, messagetext, datetimeentry, status
        ) VALUES(
            '$messageIdEsc', '$requestIdEsc', '$senderIdEsc', '$senderRoleEsc', '$messageEsc', NOW(), 'active'
        )");
    $_SESSION['Message'] = $sql
        ? gc_flash_html('success', 'Message sent in this counselling case.')
        : gc_flash_html('error', 'Message could not be sent.');
    gc_redirect($caseQuery);
}

if($isTeacherView && isset($_POST['update_counselling_case'])){
    $requestId = isset($_POST['requestid']) ? trim((string)$_POST['requestid']) : '';
    $scheduleViewDate = isset($_POST['schedule_date']) ? gc_normalize_date($_POST['schedule_date']) : date('Y-m-d');
    $caseQuery = '?case='.rawurlencode($requestId).'&schedule_date='.rawurlencode($scheduleViewDate);
    $status = strtolower(trim((string)(isset($_POST['status']) ? $_POST['status'] : 'pending')));
    $sessionMode = trim((string)(isset($_POST['sessionmode']) ? $_POST['sessionmode'] : ''));
    $scheduledDate = trim((string)(isset($_POST['scheduled_date']) ? $_POST['scheduled_date'] : ''));
    $scheduledTime = trim((string)(isset($_POST['scheduled_time']) ? $_POST['scheduled_time'] : ''));
    $scheduledEndTime = trim((string)(isset($_POST['scheduled_endtime']) ? $_POST['scheduled_endtime'] : ''));
    $meetingLink = trim((string)(isset($_POST['meetinglink']) ? $_POST['meetinglink'] : ''));
    $venue = trim((string)(isset($_POST['venue']) ? $_POST['venue'] : ''));
    $statusNote = trim((string)(isset($_POST['statusnote']) ? $_POST['statusnote'] : ''));

    $requestRow = counselling_request_row($con, $requestId);
    if(!$requestRow || !counselling_user_can_view_request($con, $requestId, $currentUserId, 'teacher')){
        $_SESSION['Message'] = gc_flash_html('error', 'That counselling case could not be updated.');
        gc_redirect();
    }
    if(!in_array($status, array('pending', 'accepted', 'rescheduled', 'completed', 'declined', 'cancelled'), true)){
        $_SESSION['Message'] = gc_flash_html('error', 'Choose a valid case status.');
        gc_redirect($caseQuery);
    }
    if(($status === 'accepted' || $status === 'rescheduled') && ($scheduledDate === '' || $scheduledTime === '')){
        $_SESSION['Message'] = gc_flash_html('error', 'Accepted or rescheduled cases need a confirmed date and time.');
        gc_redirect($caseQuery);
    }
    if($sessionMode === 'online' && $status !== 'declined' && $meetingLink === ''){
        $_SESSION['Message'] = gc_flash_html('error', 'Add the meeting link for an online counselling session.');
        gc_redirect($caseQuery);
    }

    $previousStatus = strtolower(trim((string)(isset($requestRow['status']) ? $requestRow['status'] : 'pending')));
    $previousScheduledDate = trim((string)(isset($requestRow['scheduled_date']) ? $requestRow['scheduled_date'] : ''));
    $previousScheduledTime = trim((string)(isset($requestRow['scheduled_time']) ? $requestRow['scheduled_time'] : ''));
    $previousScheduledEndTime = trim((string)(isset($requestRow['scheduled_endtime']) ? $requestRow['scheduled_endtime'] : ''));
    $resetReminderState = false;
    if(in_array($status, array('accepted', 'rescheduled'), true)){
        if($previousStatus !== $status
            || $previousScheduledDate !== $scheduledDate
            || $previousScheduledTime !== $scheduledTime
            || $previousScheduledEndTime !== $scheduledEndTime){
            $resetReminderState = true;
        }
    }

    $requestIdEsc = mysqli_real_escape_string($con, $requestId);
    $statusEsc = mysqli_real_escape_string($con, $status);
    $sessionModeEsc = mysqli_real_escape_string($con, substr($sessionMode, 0, 20));
    $scheduledDateSql = $scheduledDate !== '' ? "'".mysqli_real_escape_string($con, $scheduledDate)."'" : 'NULL';
    $scheduledTimeSql = $scheduledTime !== '' ? "'".mysqli_real_escape_string($con, $scheduledTime)."'" : 'NULL';
    $scheduledEndTimeSql = $scheduledEndTime !== '' ? "'".mysqli_real_escape_string($con, $scheduledEndTime)."'" : 'NULL';
    $meetingLinkSql = $meetingLink !== '' ? "'".mysqli_real_escape_string($con, substr($meetingLink, 0, 255))."'" : 'NULL';
    $venueSql = $venue !== '' ? "'".mysqli_real_escape_string($con, substr($venue, 0, 150))."'" : 'NULL';
    $statusNoteSql = $statusNote !== '' ? "'".mysqli_real_escape_string($con, $statusNote)."'" : 'NULL';
    $recordedByEsc = mysqli_real_escape_string($con, $currentUserId);
    $reminderResetSql = $resetReminderState ? ",
            counsellorremindersentat=NULL,
            counsellorreminderstatus=NULL,
            counsellorreminderattemptat=NULL" : "";

    $sql = mysqli_query($con, "UPDATE tblcounsellingrequest
        SET status='$statusEsc',
            sessionmode='$sessionModeEsc',
            scheduled_date=$scheduledDateSql,
            scheduled_time=$scheduledTimeSql,
            scheduled_endtime=$scheduledEndTimeSql,
            meetinglink=$meetingLinkSql,
            venue=$venueSql,
            statusnote=$statusNoteSql,
            updatedat=NOW(),
            recordedby='$recordedByEsc'".$reminderResetSql."
        WHERE requestid='$requestIdEsc'
          AND counsellorid='$recordedByEsc'");

    $_SESSION['Message'] = $sql
        ? gc_flash_html('success', 'Counselling case updated.')
        : gc_flash_html('error', 'Counselling case could not be updated.');
    gc_redirect($caseQuery);
}

$flashHtml = $_SESSION['Message'];
$_SESSION['Message'] = '';

$studentAssignment = $isStudentView ? counselling_resolve_student_assignment($con, $currentUserId) : null;
$studentRequestRows = $isStudentView ? counselling_student_request_rows($con, $currentUserId) : array();
$teacherRequestRows = $isTeacherView ? counselling_counsellor_request_rows($con, $currentUserId) : array();
$caseRows = $isStudentView ? $studentRequestRows : $teacherRequestRows;
$caseCounts = counselling_case_counts($caseRows);
$teacherAssignmentSummary = $isTeacherView ? counselling_teacher_assignment_summary($con, $currentUserId) : array();
$teacherStudentOptions = $isTeacherView ? counselling_counsellor_student_rows($con, $currentUserId) : array();
$teacherHasSchoolScope = $isTeacherView && isset($teacherAssignmentSummary['school_scope_count']) && (int)$teacherAssignmentSummary['school_scope_count'] > 0;
$teacherStudentClasses = array();
$teacherStudentBatches = array();
if($isTeacherView && !empty($teacherStudentOptions)){
    foreach($teacherStudentOptions as $_TeacherStudentOption){
        $_StudentClassName = trim((string)(isset($_TeacherStudentOption['class_name']) ? $_TeacherStudentOption['class_name'] : ''));
        $_StudentBatchName = trim((string)(isset($_TeacherStudentOption['batch']) ? $_TeacherStudentOption['batch'] : ''));
        if($_StudentClassName !== ''){
            $teacherStudentClasses[$_StudentClassName] = $_StudentClassName;
        }
        if($_StudentBatchName !== ''){
            $teacherStudentBatches[$_StudentBatchName] = $_StudentBatchName;
        }
    }
    natcasesort($teacherStudentClasses);
    natcasesort($teacherStudentBatches);
}
$scheduleDate = $isTeacherView ? gc_normalize_date(isset($_GET['schedule_date']) ? $_GET['schedule_date'] : '', date('Y-m-d')) : '';
$scheduleWeekStart = $isTeacherView ? date('Y-m-d', strtotime('monday this week', strtotime($scheduleDate))) : '';
$scheduleWeekEnd = $isTeacherView ? date('Y-m-d', strtotime($scheduleWeekStart.' +6 days')) : '';
$teacherDayScheduleRows = $isTeacherView ? counselling_counsellor_timetable_rows($con, $currentUserId, $scheduleDate, $scheduleDate) : array();
$teacherWeekScheduleRows = $isTeacherView ? counselling_counsellor_timetable_rows($con, $currentUserId, $scheduleWeekStart, $scheduleWeekEnd) : array();
$teacherWeekScheduleGroups = $isTeacherView ? gc_group_schedule_rows($teacherWeekScheduleRows) : array();
$schedulePrevDate = $isTeacherView ? date('Y-m-d', strtotime($scheduleDate.' -1 day')) : '';
$scheduleNextDate = $isTeacherView ? date('Y-m-d', strtotime($scheduleDate.' +1 day')) : '';
$todayDate = $isTeacherView ? date('Y-m-d') : '';

$selectedCaseId = isset($_GET['case']) ? trim((string)$_GET['case']) : '';
if($selectedCaseId === '' && !empty($caseRows)){
    $selectedCaseId = trim((string)$caseRows[0]['requestid']);
}

$selectedCase = null;
$selectedThreadRows = array();
$selectedStudentContext = null;
if($selectedCaseId !== '' && counselling_user_can_view_request($con, $selectedCaseId, $currentUserId, $viewerRole)){
    $selectedCase = counselling_request_row($con, $selectedCaseId);
    $selectedThreadRows = counselling_thread_rows($con, $selectedCaseId);
    if($isTeacherView && $selectedCase){
        $selectedStudentContext = counselling_student_context($con, isset($selectedCase['studentid']) ? $selectedCase['studentid'] : '');
    }
}

$pageTitle = $isStudentView ? 'Guidance & Counselling' : 'Counselling Cases';
$heroTitle = $isStudentView ? 'Book A Counselling Session' : 'Manage Counselling Cases';
$heroSummary = $isStudentView
    ? 'Request a private session with your dedicated counsellor, follow the response, and keep the conversation in one secure case.'
    : 'Review student counselling requests, respond privately, and use the student’s academic context to guide the session well.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/guidance-counselling.css">
<script src="scripts/guidance-counselling.js" defer></script>
</head>
<body class="guidance-counselling-page guidance-counselling-page--<?php echo counselling_esc($viewerRole); ?>">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <main class="gc-shell">
        <section class="gc-hero">
            <div class="gc-hero__copy">
                <span class="gc-kicker"><i class="fa fa-heartbeat"></i> <?php echo counselling_esc($pageTitle); ?></span>
                <h1><?php echo counselling_esc($heroTitle); ?></h1>
                <p><?php echo counselling_esc($heroSummary); ?></p>
                <?php if($isStudentView && $studentAssignment){ ?>
                <div class="gc-chip-row">
                    <span class="gc-chip"><i class="fa fa-user"></i> Counsellor: <?php echo counselling_esc(counselling_person_name($studentAssignment)); ?></span>
                    <?php if(trim((string)$studentAssignment['assignmenttype']) === 'school'){ ?>
                    <span class="gc-chip"><i class="fa fa-university"></i> Whole School Assignment</span>
                    <?php }elseif(!empty($studentAssignment['student_scope']) && is_array($studentAssignment['student_scope'])){ ?>
                    <span class="gc-chip"><i class="fa fa-building"></i> <?php echo counselling_esc(trim((string)$studentAssignment['student_scope']['class_name']).' · '.trim((string)$studentAssignment['student_scope']['batch'])); ?></span>
                    <?php } ?>
                </div>
                <?php }elseif($isTeacherView){ ?>
                <div class="gc-chip-row">
                    <span class="gc-chip"><i class="fa fa-university"></i> School Scope: <?php echo number_format((int)$teacherAssignmentSummary['school_scope_count']); ?></span>
                    <span class="gc-chip"><i class="fa fa-sitemap"></i> Class Scopes: <?php echo number_format((int)$teacherAssignmentSummary['class_scope_count']); ?></span>
                    <span class="gc-chip"><i class="fa fa-user-secret"></i> Student Overrides: <?php echo number_format((int)$teacherAssignmentSummary['student_override_count']); ?></span>
                    <span class="gc-chip"><i class="fa fa-calendar"></i> Today: <?php echo number_format((int)count($teacherDayScheduleRows)); ?></span>
                    <span class="gc-chip"><i class="fa fa-calendar-o"></i> This Week: <?php echo number_format((int)count($teacherWeekScheduleRows)); ?></span>
                </div>
                <?php } ?>
            </div>
            <div class="gc-stats">
                <?php if($isStudentView){ ?>
                <article class="gc-stat">
                    <span>Total Requests</span>
                    <strong><?php echo number_format((int)count($studentRequestRows)); ?></strong>
                </article>
                <article class="gc-stat">
                    <span>Pending</span>
                    <strong><?php echo number_format((int)$caseCounts['pending']); ?></strong>
                </article>
                <article class="gc-stat">
                    <span>Accepted</span>
                    <strong><?php echo number_format((int)$caseCounts['accepted'] + (int)$caseCounts['rescheduled']); ?></strong>
                </article>
                <article class="gc-stat">
                    <span>Completed</span>
                    <strong><?php echo number_format((int)$caseCounts['completed']); ?></strong>
                </article>
                <?php }else{ ?>
                <article class="gc-stat">
                    <span>Open Cases</span>
                    <strong><?php echo number_format((int)$caseCounts['pending'] + (int)$caseCounts['accepted'] + (int)$caseCounts['rescheduled']); ?></strong>
                </article>
                <article class="gc-stat">
                    <span>Pending</span>
                    <strong><?php echo number_format((int)$caseCounts['pending']); ?></strong>
                </article>
                <article class="gc-stat">
                    <span>Completed</span>
                    <strong><?php echo number_format((int)$caseCounts['completed']); ?></strong>
                </article>
                <article class="gc-stat">
                    <span>Declined</span>
                    <strong><?php echo number_format((int)$caseCounts['declined']); ?></strong>
                </article>
                <?php } ?>
            </div>
        </section>

        <?php if($flashHtml !== ''){ ?>
        <div class="gc-message-stack"><?php echo $flashHtml; ?></div>
        <?php } ?>

        <?php if($isStudentView){ ?>
        <div class="gc-layout">
            <aside class="gc-sidebar">
                <section class="gc-surface">
                    <div class="gc-panel-head">
                        <div>
                            <span class="gc-panel-kicker">Dedicated Counsellor</span>
                            <h2>Your Assigned Support</h2>
                            <p>Only your dedicated counsellor will receive this request and the follow-up messages in the case.</p>
                        </div>
                    </div>
                    <?php if($studentAssignment){ ?>
                    <div class="gc-summary-card">
                        <h3><?php echo counselling_esc(counselling_person_name($studentAssignment)); ?></h3>
                        <p><?php
                            $_AssignmentType = trim((string)$studentAssignment['assignmenttype']);
                            if($_AssignmentType === 'student'){
                                echo 'Student-specific counsellor override';
                            }elseif($_AssignmentType === 'school'){
                                echo 'School-wide counsellor assignment';
                            }else{
                                echo 'Class-based counsellor assignment';
                            }
                        ?></p>
                    </div>
                    <form method="post" action="guidance-counselling.php" class="gc-form">
                        <label class="gc-field">
                            <span>Category</span>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <option value="academic">Academic Support</option>
                                <option value="personal">Personal Support</option>
                                <option value="welfare">Welfare</option>
                                <option value="discipline">Discipline</option>
                                <option value="bullying">Bullying</option>
                                <option value="career">Career Guidance</option>
                                <option value="health">Health</option>
                                <option value="other">Other</option>
                            </select>
                        </label>
                        <div class="gc-form-grid">
                            <label class="gc-field">
                                <span>Preferred Session Type</span>
                                <select name="sessionmode" required>
                                    <option value="">Select Session Type</option>
                                    <option value="in_person">In Person</option>
                                    <option value="phone">Phone</option>
                                    <option value="online">Online</option>
                                </select>
                            </label>
                            <label class="gc-field">
                                <span>Urgency</span>
                                <select name="urgency">
                                    <option value="normal">Normal</option>
                                    <option value="low">Low</option>
                                    <option value="high">High</option>
                                </select>
                            </label>
                        </div>
                        <label class="gc-field">
                            <span>Subject</span>
                            <input type="text" name="subjectline" maxlength="120" placeholder="Short case title">
                        </label>
                        <div class="gc-form-grid">
                            <label class="gc-field">
                                <span>Preferred Date</span>
                                <input type="date" name="preferred_date" required>
                            </label>
                            <label class="gc-field">
                                <span>Preferred Time</span>
                                <input type="time" name="preferred_time" required>
                            </label>
                        </div>
                        <label class="gc-field">
                            <span>Request Note</span>
                            <textarea name="concern" rows="5" placeholder="Explain briefly what support you need." required></textarea>
                        </label>
                        <div class="gc-inline-note">
                            <i class="fa fa-lock"></i>
                            <span>Your request stays inside this counselling case. Avoid using the general message board for personal counselling matters.</span>
                        </div>
                        <button class="gc-btn gc-btn--primary" type="submit" name="submit_counselling_request"><i class="fa fa-save"></i> Submit Request</button>
                    </form>
                    <?php } else { ?>
                    <div class="gc-empty-state gc-empty-state--inline">
                        <h3>No counsellor assigned yet</h3>
                        <p>Administration still needs to assign your dedicated counsellor before you can book a session here.</p>
                    </div>
                    <?php } ?>
                </section>
            </aside>

            <section class="gc-main">
                <section class="gc-surface">
                    <div class="gc-panel-head">
                        <div>
                            <span class="gc-panel-kicker">My Cases</span>
                            <h2>Request History</h2>
                            <p>Follow your counselling requests, sessions arranged for you by the counsellor, and the private conversation inside each case.</p>
                        </div>
                    </div>

                    <?php if(empty($studentRequestRows)){ ?>
                    <div class="gc-empty-state">
                        <h3>No counselling request yet</h3>
                        <p>Your submitted requests and counsellor-arranged sessions will appear here after the first case is opened.</p>
                    </div>
                    <?php } else { ?>
                    <div class="gc-case-grid">
                        <?php foreach($studentRequestRows as $requestRow){ ?>
                        <?php $statusMeta = counselling_status_meta(isset($requestRow['status']) ? $requestRow['status'] : 'pending'); ?>
                        <a class="gc-case-card<?php echo $selectedCase && trim((string)$selectedCase['requestid']) === trim((string)$requestRow['requestid']) ? ' is-active' : ''; ?>" href="<?php echo counselling_esc(gc_case_link($requestRow['requestid'])); ?>">
                            <div class="gc-case-card__top">
                                <span class="gc-status gc-status--<?php echo counselling_esc($statusMeta['class']); ?>"><?php echo counselling_esc($statusMeta['label']); ?></span>
                                <span class="gc-case-card__time"><?php echo counselling_esc(counselling_format_datetime($requestRow['createdat'])); ?></span>
                            </div>
                            <h3><?php echo counselling_esc(trim((string)$requestRow['subjectline']) !== '' ? (string)$requestRow['subjectline'] : gc_category_label($requestRow['category'])); ?></h3>
                            <p><?php echo counselling_esc(gc_mode_label($requestRow['sessionmode']).' · '.gc_urgency_label($requestRow['urgency'])); ?></p>
                        </a>
                        <?php } ?>
                    </div>

                    <?php if($selectedCase){ ?>
                    <div class="gc-detail-card">
                        <?php $detailStatus = counselling_status_meta(isset($selectedCase['status']) ? $selectedCase['status'] : 'pending'); ?>
                        <div class="gc-detail-card__header">
                            <div>
                                <span class="gc-panel-kicker">Selected Case</span>
                                <h2><?php echo counselling_esc(trim((string)$selectedCase['subjectline']) !== '' ? (string)$selectedCase['subjectline'] : gc_category_label($selectedCase['category'])); ?></h2>
                            </div>
                            <span class="gc-status gc-status--<?php echo counselling_esc($detailStatus['class']); ?>"><?php echo counselling_esc($detailStatus['label']); ?></span>
                        </div>
                        <div class="gc-detail-grid">
                            <div><span>Counsellor</span><strong><?php echo counselling_esc(isset($selectedCase['counsellor_name']) ? $selectedCase['counsellor_name'] : ''); ?></strong></div>
                            <div><span>Category</span><strong><?php echo counselling_esc(gc_category_label($selectedCase['category'])); ?></strong></div>
                            <div><span>Preferred Session</span><strong><?php echo counselling_esc(gc_mode_label($selectedCase['sessionmode'])); ?></strong></div>
                            <div><span>Preferred Schedule</span><strong><?php echo counselling_esc(gc_request_schedule_text($selectedCase, 'preferred')); ?></strong></div>
                            <div><span>Confirmed Schedule</span><strong><?php echo counselling_esc(gc_request_schedule_text($selectedCase, 'scheduled')); ?></strong></div>
                            <div><span>Venue / Link</span><strong><?php echo counselling_esc(gc_request_location_text($selectedCase)); ?></strong></div>
                        </div>
                        <div class="gc-note-box">
                            <span>Case Note</span>
                            <p><?php echo nl2br(counselling_esc($selectedCase['concern'])); ?></p>
                        </div>
                        <?php if(trim((string)$selectedCase['statusnote']) !== ''){ ?>
                        <div class="gc-note-box gc-note-box--alt">
                            <span>Counsellor Note</span>
                            <p><?php echo nl2br(counselling_esc($selectedCase['statusnote'])); ?></p>
                        </div>
                        <?php } ?>
                        <?php if(counselling_request_active($selectedCase['status'])){ ?>
                        <section class="gc-thread">
                            <div class="gc-thread__header">
                                <h3>Appointment Change</h3>
                            </div>
                            <form method="post" action="<?php echo counselling_esc(gc_case_link($selectedCase['requestid'])); ?>" class="gc-form gc-form--appointment" data-action-form>
                                <input type="hidden" name="requestid" value="<?php echo counselling_esc((string)$selectedCase['requestid']); ?>">
                                <div class="gc-form-grid">
                                    <label class="gc-field gc-field--action">
                                        <span>Appointment Action</span>
                                        <select name="student_case_action" data-action-select>
                                            <option value="reschedule">Request Another Day</option>
                                            <option value="cancel">Cancel This Appointment</option>
                                        </select>
                                    </label>
                                    <label class="gc-field" data-reschedule-only>
                                        <span>New Date</span>
                                        <input type="date" name="requested_date" value="<?php echo counselling_esc((string)$selectedCase['scheduled_date']); ?>">
                                    </label>
                                </div>
                                <div class="gc-inline-note gc-inline-note--action" data-action-note>
                                    Choose <strong>Request Another Day</strong> to suggest a new meeting time. Choose <strong>Cancel This Appointment</strong> to close this appointment.
                                </div>
                                <div class="gc-form-grid" data-reschedule-grid>
                                    <label class="gc-field" data-reschedule-only>
                                        <span>New Time</span>
                                        <input type="time" name="requested_time" value="<?php echo counselling_esc(trim((string)$selectedCase['scheduled_time']) !== '' ? substr((string)$selectedCase['scheduled_time'], 0, 5) : ''); ?>">
                                    </label>
                                    <label class="gc-field" data-reschedule-only>
                                        <span>End Time</span>
                                        <input type="time" name="requested_endtime" value="<?php echo counselling_esc(trim((string)$selectedCase['scheduled_endtime']) !== '' ? substr((string)$selectedCase['scheduled_endtime'], 0, 5) : ''); ?>">
                                    </label>
                                </div>
                                <label class="gc-field">
                                    <span>Reason</span>
                                    <textarea name="request_note" rows="3" placeholder="Explain briefly why you need a new date or why you are cancelling."></textarea>
                                </label>
                                <button class="gc-btn gc-btn--secondary" type="submit" name="student_manage_counselling_case" data-action-submit><i class="fa fa-calendar-times-o"></i> Send Appointment Request</button>
                            </form>
                        </section>
                        <?php } ?>

                        <section class="gc-thread">
                            <div class="gc-thread__header">
                                <h3>Case Messages</h3>
                            </div>
                            <div class="gc-thread__feed">
                                <?php if(empty($selectedThreadRows)){ ?>
                                <div class="gc-empty-state gc-empty-state--inline">
                                    <h3>No messages yet</h3>
                                    <p>Use the private message box below if you need to add more information for this case.</p>
                                </div>
                                <?php } else { ?>
                                <?php foreach($selectedThreadRows as $threadRow){ ?>
                                <article class="gc-thread-item<?php echo trim((string)$threadRow['senderrole']) === 'student' ? ' gc-thread-item--student' : ' gc-thread-item--teacher'; ?>">
                                    <div class="gc-thread-item__meta">
                                        <strong><?php echo counselling_esc(trim((string)$threadRow['senderrole']) === 'student' ? 'You' : 'Counsellor'); ?></strong>
                                        <span><?php echo counselling_esc(counselling_format_datetime($threadRow['datetimeentry'])); ?></span>
                                    </div>
                                    <p><?php echo nl2br(counselling_esc($threadRow['messagetext'])); ?></p>
                                </article>
                                <?php } ?>
                                <?php } ?>
                            </div>
                            <?php if(counselling_request_active($selectedCase['status'])){ ?>
                            <form method="post" action="<?php echo counselling_esc(gc_case_link($selectedCase['requestid'])); ?>" class="gc-thread-form">
                                <input type="hidden" name="requestid" value="<?php echo counselling_esc((string)$selectedCase['requestid']); ?>">
                                <textarea name="messagetext" rows="3" placeholder="Add a private message for your counsellor"></textarea>
                                <button class="gc-btn gc-btn--secondary" type="submit" name="send_counselling_message"><i class="fa fa-send"></i> Send Message</button>
                            </form>
                            <?php } ?>
                        </section>
                    </div>
                    <?php } ?>
                    <?php } ?>
                </section>
            </section>
        </div>
        <?php } else { ?>
        <div class="gc-layout gc-layout--teacher">
            <section class="gc-main">
                <section class="gc-surface">
                    <div class="gc-panel-head">
                        <div>
                            <span class="gc-panel-kicker">Timetable</span>
                            <h2>Day And Week Schedule</h2>
                            <p>Check your counselling itinerary for the selected day and the rest of the week from one place.</p>
                        </div>
                    </div>
                    <div class="gc-timetable-toolbar">
                        <div class="gc-chip-row gc-chip-row--compact">
                            <span class="gc-chip"><i class="fa fa-calendar"></i> Selected Day: <?php echo counselling_esc(date('D, d M Y', strtotime($scheduleDate))); ?></span>
                            <span class="gc-chip"><i class="fa fa-calendar-o"></i> Week: <?php echo counselling_esc(date('d M', strtotime($scheduleWeekStart)).' - '.date('d M Y', strtotime($scheduleWeekEnd))); ?></span>
                        </div>
                        <div class="gc-timetable-actions">
                            <a class="gc-btn gc-btn--secondary" href="<?php echo counselling_esc(gc_page_link(array('schedule_date' => $schedulePrevDate, 'case' => $selectedCaseId))); ?>"><i class="fa fa-chevron-left"></i> Previous Day</a>
                            <a class="gc-btn gc-btn--secondary" href="<?php echo counselling_esc(gc_page_link(array('schedule_date' => $todayDate, 'case' => $selectedCaseId))); ?>"><i class="fa fa-dot-circle-o"></i> Today</a>
                            <a class="gc-btn gc-btn--secondary" href="<?php echo counselling_esc(gc_page_link(array('schedule_date' => $scheduleNextDate, 'case' => $selectedCaseId))); ?>">Next Day <i class="fa fa-chevron-right"></i></a>
                        </div>
                    </div>
                    <div class="gc-timetable-grid">
                        <section class="gc-timetable-column">
                            <div class="gc-timetable-column__head">
                                <div>
                                    <h3><?php echo counselling_esc(date('l, d M Y', strtotime($scheduleDate))); ?></h3>
                                    <p><?php echo counselling_esc((int)count($teacherDayScheduleRows) === 1 ? '1 session planned' : number_format((int)count($teacherDayScheduleRows)).' sessions planned'); ?></p>
                                </div>
                            </div>
                            <?php if(empty($teacherDayScheduleRows)){ ?>
                            <div class="gc-empty-state gc-empty-state--inline">
                                <h3>No session set for this day</h3>
                                <p>Confirmed counselling sessions for the selected day will appear here after you schedule them.</p>
                            </div>
                            <?php } else { ?>
                            <div class="gc-timetable-stack">
                                <?php foreach($teacherDayScheduleRows as $scheduleRow){ ?>
                                <?php $scheduleStatus = counselling_status_meta(isset($scheduleRow['status']) ? $scheduleRow['status'] : 'pending'); ?>
                                <article class="gc-timetable-card">
                                    <div class="gc-timetable-card__top">
                                        <span class="gc-status gc-status--<?php echo counselling_esc($scheduleStatus['class']); ?>"><?php echo counselling_esc($scheduleStatus['label']); ?></span>
                                        <span class="gc-timetable-card__time"><?php echo counselling_esc(gc_timetable_slot_text($scheduleRow)); ?></span>
                                    </div>
                                    <h4><?php echo counselling_esc(counselling_person_name($scheduleRow)); ?></h4>
                                    <p><?php echo counselling_esc((trim((string)$scheduleRow['subjectline']) !== '' ? trim((string)$scheduleRow['subjectline']) : gc_category_label($scheduleRow['category'])).' / '.gc_mode_label($scheduleRow['sessionmode'])); ?></p>
                                    <div class="gc-timetable-card__meta">
                                        <span><i class="fa fa-id-card-o"></i> <?php echo counselling_esc((string)$scheduleRow['studentid']); ?></span>
                                        <span><i class="fa fa-map-marker"></i> <?php echo counselling_esc(gc_request_location_text($scheduleRow)); ?></span>
                                    </div>
                                    <div class="gc-timetable-card__actions">
                                        <a class="gc-btn gc-btn--secondary" href="<?php echo counselling_esc(gc_page_link(array('case' => $scheduleRow['requestid'], 'schedule_date' => $scheduleDate))); ?>"><i class="fa fa-folder-open-o"></i> Open Case</a>
                                        <?php if(trim((string)$scheduleRow['sessionmode']) === 'online' && trim((string)$scheduleRow['meetinglink']) !== ''){ ?>
                                        <a class="gc-btn gc-btn--secondary" href="<?php echo counselling_esc((string)$scheduleRow['meetinglink']); ?>" target="_blank" rel="noopener"><i class="fa fa-video-camera"></i> Join Link</a>
                                        <?php } ?>
                                    </div>
                                </article>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </section>

                        <section class="gc-timetable-column">
                            <div class="gc-timetable-column__head">
                                <div>
                                    <h3>This Week</h3>
                                    <p><?php echo counselling_esc((int)count($teacherWeekScheduleRows) === 1 ? '1 session in view' : number_format((int)count($teacherWeekScheduleRows)).' sessions in view'); ?></p>
                                </div>
                            </div>
                            <?php if(empty($teacherWeekScheduleRows)){ ?>
                            <div class="gc-empty-state gc-empty-state--inline">
                                <h3>No sessions scheduled this week</h3>
                                <p>Once you accept or reschedule cases with a confirmed date and time, they will appear in this weekly view.</p>
                            </div>
                            <?php } else { ?>
                            <div class="gc-week-group-stack">
                                <?php foreach($teacherWeekScheduleGroups as $weekDate => $weekDateRows){ ?>
                                <section class="gc-week-group<?php echo $weekDate === $scheduleDate ? ' is-active' : ''; ?>">
                                    <div class="gc-week-group__head">
                                        <strong><?php echo counselling_esc(date('D, d M Y', strtotime($weekDate))); ?></strong>
                                        <span><?php echo counselling_esc(number_format((int)count($weekDateRows)).((int)count($weekDateRows) === 1 ? ' session' : ' sessions')); ?></span>
                                    </div>
                                    <div class="gc-week-group__body">
                                        <?php foreach($weekDateRows as $weekRow){ ?>
                                        <?php $weekStatus = counselling_status_meta(isset($weekRow['status']) ? $weekRow['status'] : 'pending'); ?>
                                        <a class="gc-week-item<?php echo $selectedCase && trim((string)$selectedCase['requestid']) === trim((string)$weekRow['requestid']) ? ' is-active' : ''; ?>" href="<?php echo counselling_esc(gc_page_link(array('case' => $weekRow['requestid'], 'schedule_date' => $weekDate))); ?>">
                                            <div class="gc-week-item__time"><?php echo counselling_esc(gc_timetable_slot_text($weekRow)); ?></div>
                                            <div class="gc-week-item__body">
                                                <strong><?php echo counselling_esc(counselling_person_name($weekRow)); ?></strong>
                                                <span><?php echo counselling_esc((trim((string)$weekRow['subjectline']) !== '' ? trim((string)$weekRow['subjectline']) : gc_category_label($weekRow['category'])).' / '.gc_mode_label($weekRow['sessionmode'])); ?></span>
                                            </div>
                                            <span class="gc-status gc-status--<?php echo counselling_esc($weekStatus['class']); ?>"><?php echo counselling_esc($weekStatus['label']); ?></span>
                                        </a>
                                        <?php } ?>
                                    </div>
                                </section>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </section>
                    </div>
                </section>

                <section class="gc-surface">
                    <div class="gc-panel-head">
                        <div>
                            <span class="gc-panel-kicker">Organise Session</span>
                            <h2>Create A Counselling Session</h2>
                            <p><?php echo counselling_esc($teacherHasSchoolScope ? 'Arrange a counselling appointment for any active student in the school when you need to call in a student directly.' : 'Arrange a counselling appointment yourself when you need to call in a student instead of waiting for the student to request one.'); ?></p>
                        </div>
                    </div>
                    <?php if(empty($teacherStudentOptions)){ ?>
                    <div class="gc-empty-state gc-empty-state--inline">
                        <h3>No student available yet</h3>
                        <p>Students in your counselling scope will appear here once they are assigned to you.</p>
                    </div>
                    <?php } else { ?>
                    <form method="post" action="guidance-counselling.php" class="gc-form">
                        <div class="gc-student-directory" data-student-directory data-student-scope-label="<?php echo counselling_esc($teacherHasSchoolScope ? 'active students in the whole school' : 'students in your counselling scope'); ?>" data-student-placeholder="<?php echo counselling_esc($teacherHasSchoolScope ? 'Select Student From Whole School' : 'Select Student From List'); ?>">
                            <div class="gc-student-directory__filters">
                                <label class="gc-field">
                                    <span><?php echo counselling_esc($teacherHasSchoolScope ? 'Search All Active Students' : 'Search Student'); ?></span>
                                    <input type="search" data-student-filter placeholder="<?php echo counselling_esc($teacherHasSchoolScope ? 'Search the whole school by name, ID, class, or batch' : 'Search by name, ID, class, or batch'); ?>" autocomplete="off">
                                </label>
                                <label class="gc-field">
                                    <span>Class Filter</span>
                                    <select data-student-class-filter>
                                        <option value="">All Classes</option>
                                        <?php foreach($teacherStudentClasses as $_TeacherStudentClass){ ?>
                                        <option value="<?php echo counselling_esc(strtolower((string)$_TeacherStudentClass)); ?>"><?php echo counselling_esc((string)$_TeacherStudentClass); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <label class="gc-field">
                                    <span>Batch Filter</span>
                                    <select data-student-batch-filter>
                                        <option value="">All Batches</option>
                                        <?php foreach($teacherStudentBatches as $_TeacherStudentBatch){ ?>
                                        <option value="<?php echo counselling_esc(strtolower((string)$_TeacherStudentBatch)); ?>"><?php echo counselling_esc((string)$_TeacherStudentBatch); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                            </div>
                            <label class="gc-field">
                                <span><?php echo counselling_esc($teacherHasSchoolScope ? 'All Active Students' : 'Student List'); ?></span>
                                <select name="studentid" size="14" data-student-select required>
                                    <option value=""><?php echo counselling_esc($teacherHasSchoolScope ? 'Select Student From Whole School' : 'Select Student From List'); ?></option>
                                    <?php foreach($teacherStudentOptions as $studentOption){ ?>
                                    <?php
                                        $studentName = counselling_person_name($studentOption);
                                        $studentScopeText = trim((string)(isset($studentOption['class_name']) ? $studentOption['class_name'] : ''));
                                        $studentBatchText = trim((string)(isset($studentOption['batch']) ? $studentOption['batch'] : ''));
                                        $studentMetaText = trim($studentScopeText.($studentBatchText !== '' ? ' · '.$studentBatchText : ''));
                                        $studentSearchText = strtolower(trim((string)$studentOption['userid'].' '.$studentName.' '.$studentMetaText));
                                    ?>
                                    <option value="<?php echo counselling_esc((string)$studentOption['userid']); ?>" data-search="<?php echo counselling_esc($studentSearchText); ?>" data-class="<?php echo counselling_esc(strtolower($studentScopeText)); ?>" data-batch="<?php echo counselling_esc(strtolower($studentBatchText)); ?>">
                                        <?php echo counselling_esc($studentName.' - '.(string)$studentOption['userid'].($studentMetaText !== '' ? ' - '.$studentMetaText : '')); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </label>
                            <div class="gc-inline-note gc-inline-note--picker">
                                <i class="fa fa-search"></i>
                                <span data-student-picker-status>Showing all <?php echo number_format((int)count($teacherStudentOptions)); ?> <?php echo counselling_esc($teacherHasSchoolScope ? 'active students in the whole school' : 'students in your counselling scope'); ?>.</span>
                            </div>
                        </div>
                        <div class="gc-form-grid">
                            <label class="gc-field">
                                <span>Category</span>
                                <select name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="academic">Academic Support</option>
                                    <option value="personal">Personal Support</option>
                                    <option value="welfare">Welfare</option>
                                    <option value="discipline">Discipline</option>
                                    <option value="bullying">Bullying</option>
                                    <option value="career">Career Guidance</option>
                                    <option value="health">Health</option>
                                    <option value="other">Other</option>
                                </select>
                            </label>
                            <label class="gc-field">
                                <span>Session Type</span>
                                <select name="sessionmode" required>
                                    <option value="">Select Session Type</option>
                                    <option value="in_person">In Person</option>
                                    <option value="phone">Phone</option>
                                    <option value="online">Online</option>
                                </select>
                            </label>
                        </div>
                        <div class="gc-form-grid">
                            <label class="gc-field">
                                <span>Urgency</span>
                                <select name="urgency">
                                    <option value="normal">Normal</option>
                                    <option value="low">Low</option>
                                    <option value="high">High</option>
                                </select>
                            </label>
                            <label class="gc-field">
                                <span>Subject</span>
                                <input type="text" name="subjectline" maxlength="120" placeholder="Short case title">
                            </label>
                        </div>
                        <div class="gc-form-grid">
                            <label class="gc-field">
                                <span>Scheduled Date</span>
                                <input type="date" name="scheduled_date" required>
                            </label>
                            <label class="gc-field">
                                <span>Scheduled Time</span>
                                <input type="time" name="scheduled_time" required>
                            </label>
                        </div>
                        <div class="gc-form-grid">
                            <label class="gc-field">
                                <span>End Time</span>
                                <input type="time" name="scheduled_endtime">
                            </label>
                            <label class="gc-field">
                                <span>Venue</span>
                                <input type="text" name="venue" placeholder="Office, phone number, or meeting place">
                            </label>
                        </div>
                        <label class="gc-field">
                            <span>Meeting Link</span>
                            <input type="url" name="meetinglink" placeholder="https://...">
                        </label>
                        <label class="gc-field">
                            <span>Session Note</span>
                            <textarea name="concern" rows="4" placeholder="Explain why the student is being called in and what the session will cover." required></textarea>
                        </label>
                        <button class="gc-btn gc-btn--primary" type="submit" name="create_counselling_session"><i class="fa fa-plus-circle"></i> Create Session</button>
                    </form>
                    <?php } ?>
                </section>

                <section class="gc-surface">
                    <div class="gc-panel-head">
                        <div>
                            <span class="gc-panel-kicker">Assigned Cases</span>
                            <h2>Counselling Requests</h2>
                            <p>Open a case to confirm the session, adjust the schedule, exchange private messages, and review the student's academic record.</p>
                        </div>
                    </div>
                    <?php if(empty($teacherRequestRows)){ ?>
                    <div class="gc-empty-state">
                        <h3>No counselling case yet</h3>
                        <p><?php echo ((int)$teacherAssignmentSummary['total_scope_count']) > 0 ? 'New student counselling requests will appear here when they are submitted.' : 'You do not have an active counsellor assignment yet.'; ?></p>
                    </div>
                    <?php } else { ?>
                    <div class="gc-case-grid">
                        <?php foreach($teacherRequestRows as $requestRow){ ?>
                        <?php $statusMeta = counselling_status_meta(isset($requestRow['status']) ? $requestRow['status'] : 'pending'); ?>
                        <a class="gc-case-card<?php echo $selectedCase && trim((string)$selectedCase['requestid']) === trim((string)$requestRow['requestid']) ? ' is-active' : ''; ?>" href="<?php echo counselling_esc(gc_page_link(array('case' => $requestRow['requestid'], 'schedule_date' => $scheduleDate))); ?>">
                            <div class="gc-case-card__top">
                                <span class="gc-status gc-status--<?php echo counselling_esc($statusMeta['class']); ?>"><?php echo counselling_esc($statusMeta['label']); ?></span>
                                <span class="gc-case-card__time"><?php echo counselling_esc(counselling_format_datetime($requestRow['createdat'])); ?></span>
                            </div>
                            <h3><?php echo counselling_esc(trim((string)$requestRow['subjectline']) !== '' ? (string)$requestRow['subjectline'] : gc_category_label($requestRow['category'])); ?></h3>
                            <p><?php echo counselling_esc(counselling_person_name($requestRow).' · '.gc_mode_label($requestRow['sessionmode'])); ?></p>
                        </a>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </section>
            </section>

            <aside class="gc-sidebar gc-sidebar--teacher">
                <?php if($selectedCase && $selectedStudentContext){ ?>
                <?php $detailStatus = counselling_status_meta(isset($selectedCase['status']) ? $selectedCase['status'] : 'pending'); ?>
                <section class="gc-surface">
                    <div class="gc-panel-head">
                        <div>
                            <span class="gc-panel-kicker">Case Review</span>
                            <h2><?php echo counselling_esc($selectedStudentContext['student'] ? counselling_person_name($selectedStudentContext['student']) : (string)$selectedCase['student_name']); ?></h2>
                            <p><?php echo counselling_esc(trim((string)$selectedCase['studentid'])); ?></p>
                        </div>
                        <span class="gc-status gc-status--<?php echo counselling_esc($detailStatus['class']); ?>"><?php echo counselling_esc($detailStatus['label']); ?></span>
                    </div>

                    <div class="gc-detail-grid">
                        <div><span>Category</span><strong><?php echo counselling_esc(gc_category_label($selectedCase['category'])); ?></strong></div>
                        <div><span>Urgency</span><strong><?php echo counselling_esc(gc_urgency_label($selectedCase['urgency'])); ?></strong></div>
                        <div><span>Preferred Session</span><strong><?php echo counselling_esc(gc_mode_label($selectedCase['sessionmode'])); ?></strong></div>
                        <div><span>Preferred Schedule</span><strong><?php echo counselling_esc(gc_request_schedule_text($selectedCase, 'preferred')); ?></strong></div>
                        <div><span>Current Class</span><strong><?php echo counselling_esc(!empty($selectedStudentContext['class_scope']) ? trim((string)$selectedStudentContext['class_scope']['class_name']).' · '.trim((string)$selectedStudentContext['class_scope']['batch']) : 'Not available'); ?></strong></div>
                        <div><span>House</span><strong><?php echo counselling_esc(!empty($selectedStudentContext['house']['housename']) ? (string)$selectedStudentContext['house']['housename'] : 'Not assigned'); ?></strong></div>
                    </div>

                    <div class="gc-note-box">
                        <span>Case Note</span>
                        <p><?php echo nl2br(counselling_esc($selectedCase['concern'])); ?></p>
                    </div>

                    <form method="post" action="<?php echo counselling_esc(gc_page_link(array('case' => $selectedCase['requestid'], 'schedule_date' => $scheduleDate))); ?>" class="gc-form">
                        <input type="hidden" name="requestid" value="<?php echo counselling_esc((string)$selectedCase['requestid']); ?>">
                        <input type="hidden" name="schedule_date" value="<?php echo counselling_esc($scheduleDate); ?>">
                        <div class="gc-form-grid">
                            <label class="gc-field">
                                <span>Status</span>
                                <select name="status">
                                    <option value="pending"<?php echo trim((string)$selectedCase['status']) === 'pending' ? ' selected' : ''; ?>>Pending</option>
                                    <option value="accepted"<?php echo trim((string)$selectedCase['status']) === 'accepted' ? ' selected' : ''; ?>>Accepted</option>
                                    <option value="rescheduled"<?php echo trim((string)$selectedCase['status']) === 'rescheduled' ? ' selected' : ''; ?>>Rescheduled</option>
                                    <option value="completed"<?php echo trim((string)$selectedCase['status']) === 'completed' ? ' selected' : ''; ?>>Completed</option>
                                    <option value="declined"<?php echo trim((string)$selectedCase['status']) === 'declined' ? ' selected' : ''; ?>>Declined</option>
                                    <option value="cancelled"<?php echo trim((string)$selectedCase['status']) === 'cancelled' ? ' selected' : ''; ?>>Cancelled</option>
                                </select>
                            </label>
                            <label class="gc-field">
                                <span>Session Type</span>
                                <select name="sessionmode">
                                    <option value="in_person"<?php echo trim((string)$selectedCase['sessionmode']) === 'in_person' ? ' selected' : ''; ?>>In Person</option>
                                    <option value="phone"<?php echo trim((string)$selectedCase['sessionmode']) === 'phone' ? ' selected' : ''; ?>>Phone</option>
                                    <option value="online"<?php echo trim((string)$selectedCase['sessionmode']) === 'online' ? ' selected' : ''; ?>>Online</option>
                                </select>
                            </label>
                        </div>
                        <div class="gc-form-grid">
                            <label class="gc-field">
                                <span>Scheduled Date</span>
                                <input type="date" name="scheduled_date" value="<?php echo counselling_esc((string)$selectedCase['scheduled_date']); ?>">
                            </label>
                            <label class="gc-field">
                                <span>Scheduled Time</span>
                                <input type="time" name="scheduled_time" value="<?php echo counselling_esc(trim((string)$selectedCase['scheduled_time']) !== '' ? substr((string)$selectedCase['scheduled_time'], 0, 5) : ''); ?>">
                            </label>
                        </div>
                        <div class="gc-form-grid">
                            <label class="gc-field">
                                <span>End Time</span>
                                <input type="time" name="scheduled_endtime" value="<?php echo counselling_esc(trim((string)$selectedCase['scheduled_endtime']) !== '' ? substr((string)$selectedCase['scheduled_endtime'], 0, 5) : ''); ?>">
                            </label>
                            <label class="gc-field">
                                <span>Venue</span>
                                <input type="text" name="venue" value="<?php echo counselling_esc((string)$selectedCase['venue']); ?>" placeholder="Office, phone number, or meeting place">
                            </label>
                        </div>
                        <label class="gc-field">
                            <span>Meeting Link</span>
                            <input type="url" name="meetinglink" value="<?php echo counselling_esc((string)$selectedCase['meetinglink']); ?>" placeholder="https://...">
                        </label>
                        <label class="gc-field">
                            <span>Case Note</span>
                            <textarea name="statusnote" rows="4" placeholder="Record the response or next step for the student."><?php echo counselling_esc((string)$selectedCase['statusnote']); ?></textarea>
                        </label>
                        <button class="gc-btn gc-btn--primary" type="submit" name="update_counselling_case"><i class="fa fa-save"></i> Update Case</button>
                    </form>
                </section>

                <section class="gc-surface">
                    <div class="gc-panel-head">
                        <div>
                            <span class="gc-panel-kicker">Private Messages</span>
                            <h2>Case Conversation</h2>
                        </div>
                    </div>
                    <div class="gc-thread__feed">
                        <?php if(empty($selectedThreadRows)){ ?>
                        <div class="gc-empty-state gc-empty-state--inline">
                            <h3>No messages yet</h3>
                            <p>Reply here when you need more detail or want to give the student an update.</p>
                        </div>
                        <?php } else { ?>
                        <?php foreach($selectedThreadRows as $threadRow){ ?>
                        <article class="gc-thread-item<?php echo trim((string)$threadRow['senderrole']) === 'teacher' ? ' gc-thread-item--teacher' : ' gc-thread-item--student'; ?>">
                            <div class="gc-thread-item__meta">
                                <strong><?php echo counselling_esc(trim((string)$threadRow['senderrole']) === 'teacher' ? 'You' : 'Student'); ?></strong>
                                <span><?php echo counselling_esc(counselling_format_datetime($threadRow['datetimeentry'])); ?></span>
                            </div>
                            <p><?php echo nl2br(counselling_esc($threadRow['messagetext'])); ?></p>
                        </article>
                        <?php } ?>
                        <?php } ?>
                    </div>
                    <?php if(counselling_request_active($selectedCase['status'])){ ?>
                    <form method="post" action="<?php echo counselling_esc(gc_page_link(array('case' => $selectedCase['requestid'], 'schedule_date' => $scheduleDate))); ?>" class="gc-thread-form">
                        <input type="hidden" name="requestid" value="<?php echo counselling_esc((string)$selectedCase['requestid']); ?>">
                        <input type="hidden" name="schedule_date" value="<?php echo counselling_esc($scheduleDate); ?>">
                        <textarea name="messagetext" rows="3" placeholder="Send a private message to the student"></textarea>
                        <button class="gc-btn gc-btn--secondary" type="submit" name="send_counselling_message"><i class="fa fa-send"></i> Send Message</button>
                    </form>
                    <?php } ?>
                </section>

                <section class="gc-surface">
                    <div class="gc-panel-head">
                        <div>
                            <span class="gc-panel-kicker">Academic Profile</span>
                            <h2>Results Overview</h2>
                            <p>Use the student’s academic history to understand performance trends before the session.</p>
                        </div>
                    </div>
                    <?php
                    $_AcademicSummary = isset($selectedStudentContext['academic_profile']['summary']) ? $selectedStudentContext['academic_profile']['summary'] : array();
                    $_AcademicSessions = isset($selectedStudentContext['academic_profile']['sessions']) ? $selectedStudentContext['academic_profile']['sessions'] : array();
                    ?>
                    <div class="gc-academic-stats">
                        <article><span>Semesters</span><strong><?php echo number_format((int)(isset($_AcademicSummary['session_count']) ? $_AcademicSummary['session_count'] : 0)); ?></strong></article>
                        <article><span>Subjects</span><strong><?php echo number_format((int)(isset($_AcademicSummary['subject_count']) ? $_AcademicSummary['subject_count'] : 0)); ?></strong></article>
                        <article><span>Average</span><strong><?php echo isset($_AcademicSummary['average_score']) && $_AcademicSummary['average_score'] !== null ? counselling_esc(gc_subject_total($_AcademicSummary['average_score'])) : '-'; ?></strong></article>
                        <article><span>Pass Rate</span><strong><?php echo isset($_AcademicSummary['pass_rate']) && $_AcademicSummary['pass_rate'] !== null ? counselling_esc(gc_subject_total($_AcademicSummary['pass_rate'])).'%' : '-'; ?></strong></article>
                    </div>
                    <?php if(empty($_AcademicSessions)){ ?>
                    <div class="gc-empty-state gc-empty-state--inline">
                        <h3>No academic record found</h3>
                        <p>Results and terminal remarks will appear here when the student’s academic history is available.</p>
                    </div>
                    <?php } else { ?>
                    <div class="gc-session-stack">
                        <?php foreach($_AcademicSessions as $_SessionRow){ ?>
                        <details class="gc-session-card">
                            <summary>
                                <div>
                                    <strong><?php echo counselling_esc(trim((string)$_SessionRow['academic_year']).' · '.counselling_term_label($_SessionRow['termname'])); ?></strong>
                                    <span><?php echo counselling_esc(trim((string)$_SessionRow['class_name']).' · '.trim((string)$_SessionRow['batch'])); ?></span>
                                </div>
                                <span class="gc-session-card__meta"><?php echo counselling_esc((string)$_SessionRow['subject_count']); ?> subject<?php echo (int)$_SessionRow['subject_count'] === 1 ? '' : 's'; ?></span>
                            </summary>
                            <div class="gc-session-card__body">
                                <div class="gc-detail-grid">
                                    <div><span>Average</span><strong><?php echo $_SessionRow['average_score'] !== null ? counselling_esc(gc_subject_total($_SessionRow['average_score'])) : '-'; ?></strong></div>
                                    <div><span>Passes</span><strong><?php echo counselling_esc((string)$_SessionRow['pass_count']); ?></strong></div>
                                    <div><span>Updated</span><strong><?php echo counselling_esc(counselling_format_datetime($_SessionRow['last_updated'])); ?></strong></div>
                                    <div><span>Registered</span><strong><?php echo counselling_esc(counselling_format_datetime($_SessionRow['registered_on'])); ?></strong></div>
                                </div>
                                <?php if(!empty($_SessionRow['subjects'])){ ?>
                                <div class="gc-score-table-wrap">
                                    <table class="gc-score-table">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Class Score</th>
                                                <th>Exam Score</th>
                                                <th>Total</th>
                                                <th>Grade</th>
                                                <th>Result</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($_SessionRow['subjects'] as $_SubjectRow){ ?>
                                            <tr>
                                                <td data-label="Subject"><?php echo counselling_esc((string)$_SubjectRow['subject']); ?></td>
                                                <td data-label="Class Score"><?php echo counselling_esc(gc_subject_total($_SubjectRow['class_score'])); ?></td>
                                                <td data-label="Exam Score"><?php echo counselling_esc(gc_subject_total($_SubjectRow['exam_score'])); ?></td>
                                                <td data-label="Total"><?php echo counselling_esc(gc_subject_total($_SubjectRow['total_score'])); ?></td>
                                                <td data-label="Grade"><?php echo counselling_esc((string)$_SubjectRow['grade']); ?></td>
                                                <td data-label="Result"><?php echo !empty($_SubjectRow['passed']) ? 'Pass' : 'Fail'; ?></td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php } ?>
                                <?php if(!empty($_SessionRow['remarks'])){ ?>
                                <div class="gc-note-box gc-note-box--alt">
                                    <span>Terminal Remarks</span>
                                    <p><?php echo nl2br(counselling_esc(trim((string)(isset($_SessionRow['remarks']['class_teacher_remark']) ? $_SessionRow['remarks']['class_teacher_remark'] : '')).(trim((string)(isset($_SessionRow['remarks']['head_teacher_remark']) ? $_SessionRow['remarks']['head_teacher_remark'] : '')) !== '' ? "\nHead: ".trim((string)$_SessionRow['remarks']['head_teacher_remark']) : ''))); ?></p>
                                </div>
                                <?php } ?>
                            </div>
                        </details>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </section>
                <?php } else { ?>
                <section class="gc-surface">
                    <div class="gc-empty-state">
                        <h3>Select a counselling case</h3>
                        <p>Choose a case from the list to review the request, reply privately, and see the student’s academic record.</p>
                    </div>
                </section>
                <?php } ?>
            </aside>
        </div>
        <?php } ?>
    </main>
</div>
</body>
</html>
