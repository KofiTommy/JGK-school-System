<?php

if(!function_exists('um_column_exists')){
function um_column_exists($con, $table, $column){
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

if(!function_exists('ensure_user_management_columns')){
function ensure_user_management_columns($con){
    static $done = false;
    if($done || !$con){
        return;
    }
    $done = true;

    if(!um_column_exists($con, 'tblsystemuser', 'password_reset_required')){
        @mysqli_query($con, "ALTER TABLE tblsystemuser ADD COLUMN password_reset_required TINYINT(1) NOT NULL DEFAULT 0 AFTER password");
    }
    if(!um_column_exists($con, 'tblsystemuser', 'password_last_reset_at')){
        @mysqli_query($con, "ALTER TABLE tblsystemuser ADD COLUMN password_last_reset_at DATETIME NULL DEFAULT NULL AFTER password_reset_required");
    }
    if(!um_column_exists($con, 'tblsystemuser', 'module_permission_mode')){
        @mysqli_query($con, "ALTER TABLE tblsystemuser ADD COLUMN module_permission_mode VARCHAR(20) NOT NULL DEFAULT 'legacy' AFTER password_last_reset_at");
    }
    if(!um_column_exists($con, 'tblsystemuser', 'lastactivityat')){
        @mysqli_query($con, "ALTER TABLE tblsystemuser ADD COLUMN lastactivityat DATETIME NULL DEFAULT NULL AFTER module_permission_mode");
    }
    if(!um_column_exists($con, 'tblsystemuser', 'lastactivityscript')){
        @mysqli_query($con, "ALTER TABLE tblsystemuser ADD COLUMN lastactivityscript VARCHAR(120) NOT NULL DEFAULT '' AFTER lastactivityat");
    }

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblusermodulepermission (
        permissionid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        userid VARCHAR(60) NOT NULL,
        modulekey VARCHAR(80) NOT NULL,
        recordedby VARCHAR(60) DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (permissionid),
        UNIQUE KEY uq_user_module (userid,modulekey),
        KEY idx_userid (userid),
        KEY idx_modulekey (modulekey)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if(!um_column_exists($con, 'tblmessages', 'recipient_group')){
        @mysqli_query($con, "ALTER TABLE tblmessages ADD COLUMN recipient_group VARCHAR(20) NOT NULL DEFAULT 'all' AFTER sentby");
    }
    if(!um_column_exists($con, 'tblmessages', 'recipient_type')){
        @mysqli_query($con, "ALTER TABLE tblmessages ADD COLUMN recipient_type VARCHAR(30) NOT NULL DEFAULT 'group' AFTER recipient_group");
    }
    if(!um_column_exists($con, 'tblmessages', 'recipient_value')){
        @mysqli_query($con, "ALTER TABLE tblmessages ADD COLUMN recipient_value VARCHAR(150) NOT NULL DEFAULT '' AFTER recipient_type");
    }
    if(!um_column_exists($con, 'tblmessages', 'recipient_label')){
        @mysqli_query($con, "ALTER TABLE tblmessages ADD COLUMN recipient_label VARCHAR(160) NOT NULL DEFAULT '' AFTER recipient_value");
    }

    @mysqli_query($con, "UPDATE tblmessages SET recipient_type='group' WHERE TRIM(COALESCE(recipient_type,''))=''");

    @mysqli_query(
        $con,
        "UPDATE tblmessages mg
         INNER JOIN tblsystemuser su ON su.userid=mg.sentby
         SET mg.recipient_group='teachers'
         WHERE su.systemtype IN ('Teacher','Student')
           AND (mg.recipient_group='' OR mg.recipient_group='all' OR mg.recipient_group IS NULL)"
    );

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblmessageviewstate (
        userid VARCHAR(60) NOT NULL,
        lastseenat DATETIME NULL DEFAULT NULL,
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        lastupdatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (userid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensure_user_visit_log_table($con);
}
}

if(!function_exists('ensure_user_visit_log_table')){
function ensure_user_visit_log_table($con){
    static $done = false;
    if($done || !$con){
        return;
    }
    $done = true;

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tbluservisitlog (
        visitid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        userid VARCHAR(60) NOT NULL,
        fullname VARCHAR(180) NOT NULL DEFAULT '',
        accesslevel VARCHAR(40) NOT NULL DEFAULT '',
        systemtype VARCHAR(60) NOT NULL DEFAULT '',
        scriptname VARCHAR(140) NOT NULL DEFAULT '',
        requestpath VARCHAR(220) NOT NULL DEFAULT '',
        ipaddress VARCHAR(80) NOT NULL DEFAULT '',
        useragent VARCHAR(255) NOT NULL DEFAULT '',
        sessionkey VARCHAR(80) NOT NULL DEFAULT '',
        visitedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (visitid),
        KEY idx_visitedat (visitedat),
        KEY idx_userid_visitedat (userid, visitedat),
        KEY idx_systemtype_visitedat (systemtype, visitedat),
        KEY idx_scriptname_visitedat (scriptname, visitedat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
}

if(!function_exists('um_client_ip_address')){
function um_client_ip_address(){
    $candidates = array(
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    );
    foreach($candidates as $key){
        if(!empty($_SERVER[$key])){
            $value = trim((string)$_SERVER[$key]);
            if($key === 'HTTP_X_FORWARDED_FOR'){
                $parts = explode(',', $value);
                $value = trim((string)$parts[0]);
            }
            if($value !== ''){
                return substr($value, 0, 80);
            }
        }
    }
    return '';
}
}

if(!function_exists('um_log_current_user_visit')){
function um_log_current_user_visit($con, $scriptName = ''){
    ensure_user_visit_log_table($con);
    $userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    if($userId === ''){
        return false;
    }

    if($scriptName === ''){
        $scriptName = isset($_SERVER['PHP_SELF']) ? basename((string)$_SERVER['PHP_SELF']) : '';
    }
    $scriptName = substr(trim((string)$scriptName), 0, 140);
    $requestPath = isset($_SERVER['REQUEST_URI']) ? parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    $requestPath = substr(trim((string)$requestPath), 0, 220);
    $visitKey = $scriptName.'|'.$requestPath;
    $now = time();
    $lastKey = isset($_SESSION['__um_visit_last_key']) ? (string)$_SESSION['__um_visit_last_key'] : '';
    $lastTime = isset($_SESSION['__um_visit_last_time']) ? (int)$_SESSION['__um_visit_last_time'] : 0;
    if($lastKey === $visitKey && $lastTime > 0 && ($now - $lastTime) < 15){
        return true;
    }

    $fullName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : '';
    if($fullName === ''){
        $fullName = $userId;
    }
    $accessLevel = isset($_SESSION['ACCESSLEVEL']) ? trim((string)$_SESSION['ACCESSLEVEL']) : '';
    $systemType = isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
    $ipAddress = um_client_ip_address();
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(trim((string)$_SERVER['HTTP_USER_AGENT']), 0, 255) : '';
    $sessionKey = session_id() !== '' ? substr(hash('sha256', session_id()), 0, 64) : '';

    $stmt = @mysqli_prepare($con, "INSERT INTO tbluservisitlog
        (userid, fullname, accesslevel, systemtype, scriptname, requestpath, ipaddress, useragent, sessionkey, visitedat)
        VALUES (?,?,?,?,?,?,?,?,?,NOW())");
    if(!$stmt){
        return false;
    }
    mysqli_stmt_bind_param(
        $stmt,
        'sssssssss',
        $userId,
        $fullName,
        $accessLevel,
        $systemType,
        $scriptName,
        $requestPath,
        $ipAddress,
        $userAgent,
        $sessionKey
    );
    $logged = @mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if($logged){
        $_SESSION['__um_visit_last_key'] = $visitKey;
        $_SESSION['__um_visit_last_time'] = $now;
        $lastCleanup = isset($_SESSION['__um_visit_cleanup_at']) ? (int)$_SESSION['__um_visit_cleanup_at'] : 0;
        if($lastCleanup === 0 || ($now - $lastCleanup) > 1800){
            @mysqli_query($con, "DELETE FROM tbluservisitlog WHERE visitedat < (NOW() - INTERVAL 45 DAY)");
            $_SESSION['__um_visit_cleanup_at'] = $now;
        }
    }
    return (bool)$logged;
}
}

if(!function_exists('um_touch_current_user_activity')){
function um_touch_current_user_activity($con, $scriptName = ''){
    ensure_user_management_columns($con);
    $userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    if($userId === ''){
        return false;
    }

    $now = time();
    $lastTouch = isset($_SESSION['__um_last_activity_touch']) ? (int)$_SESSION['__um_last_activity_touch'] : 0;
    if($lastTouch > 0 && ($now - $lastTouch) < 60){
        return true;
    }

    if($scriptName === ''){
        $scriptName = isset($_SERVER['PHP_SELF']) ? basename((string)$_SERVER['PHP_SELF']) : '';
    }
    $userIdEsc = mysqli_real_escape_string($con, $userId);
    $scriptEsc = mysqli_real_escape_string($con, trim((string)$scriptName));
    $updated = @mysqli_query($con, "UPDATE tblsystemuser
        SET lastactivityat=NOW(), lastactivityscript='$scriptEsc'
        WHERE userid='$userIdEsc'
        LIMIT 1");
    if($updated){
        $_SESSION['__um_last_activity_touch'] = $now;
    }
    return (bool)$updated;
}
}

if(!function_exists('um_count_live_users')){
function um_count_live_users($con, $windowMinutes = 5){
    ensure_user_management_columns($con);
    $windowMinutes = max(1, min(60, (int)$windowMinutes));
    $sql = "SELECT COUNT(*) AS live_total
        FROM tblsystemuser
        WHERE lastactivityat IS NOT NULL
          AND lastactivityat >= (NOW() - INTERVAL $windowMinutes MINUTE)
          AND COALESCE(status, 'active') <> 'block'";
    $res = @mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return (int)$row['live_total'];
    }
    return 0;
}
}

if(!function_exists('um_message_normalize_audience')){
function um_message_normalize_audience($audience){
    $audience = strtolower(trim((string)$audience));
    if(in_array($audience, array('students','teachers','admins','all'), true)){
        return $audience;
    }
    return 'all';
}
}

if(!function_exists('um_message_default_audience_for_current_user')){
function um_message_default_audience_for_current_user(){
    $systemType = isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
    if($systemType === 'Student'){
        return 'admins';
    }
    if($systemType === 'Teacher'){
        return 'admins';
    }
    return 'all';
}
}

if(!function_exists('um_message_audience_options_for_current_user')){
function um_message_audience_options_for_current_user(){
    $systemType = isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
    if($systemType === 'Student'){
        return array(
            'admins' => 'Admin Only'
        );
    }
    if($systemType === 'Teacher'){
        return array(
            'admins' => 'Admin Only'
        );
    }
    return array(
        'all' => 'Everyone',
        'students' => 'Students Only',
        'teachers' => 'Teachers Only',
        'admins' => 'Admin Only'
    );
}
}

if(!function_exists('um_message_audience_label')){
function um_message_audience_label($audience){
    $audience = um_message_normalize_audience($audience);
    if($audience === 'students'){
        return 'Students Only';
    }
    if($audience === 'teachers'){
        return 'Teachers Only';
    }
    if($audience === 'admins'){
        return 'Admin Only';
    }
    return 'Everyone';
}
}

if(!function_exists('um_message_visibility_sql')){
function um_message_visibility_sql($fieldName){
    $fieldName = trim((string)$fieldName);
    if($fieldName === ''){
        $fieldName = 'recipient_group';
    }
    $systemType = isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
    if($systemType === 'Student'){
        return $fieldName." IN ('all','students')";
    }
    if($systemType === 'Teacher'){
        return $fieldName." IN ('all','teachers')";
    }
    return '1=1';
}
}

if(!function_exists('um_message_visibility_where_for_user')){
function um_message_visibility_where_for_user($con, $userId, $systemType, $alias = 'mg'){
    $userIdEsc = mysqli_real_escape_string($con, trim((string)$userId));
    $systemType = trim((string)$systemType);
    $alias = preg_replace('/[^A-Za-z0-9_]/', '', trim((string)$alias));
    if($alias === ''){
        $alias = 'mg';
    }

    if($systemType === 'Student'){
        return "(".$alias.".sentby='$userIdEsc'
            OR (COALESCE(".$alias.".recipient_type,'group')='group' AND COALESCE(".$alias.".recipient_group,'all') IN ('all','students'))
            OR (COALESCE(".$alias.".recipient_type,'group')='user' AND ".$alias.".recipient_value='$userIdEsc')
            OR (COALESCE(".$alias.".recipient_type,'group')='class_scope' AND EXISTS (
                SELECT 1
                FROM tbltermregistry tr
                WHERE tr.userid='$userIdEsc'
                  AND CONCAT(tr.class_entryid,'|',tr.batchid,'|',tr.termname)=".$alias.".recipient_value
            ))
            OR (COALESCE(".$alias.".recipient_type,'group')='house_scope' AND EXISTS (
                SELECT 1
                FROM tblstudenthouse sh
                WHERE sh.userid='$userIdEsc'
                  AND sh.status='active'
                  AND sh.houseid=".$alias.".recipient_value
            )))";
    }

    if($systemType === 'Teacher'){
        return "(".$alias.".sentby='$userIdEsc'
            OR (COALESCE(".$alias.".recipient_type,'group')='group' AND COALESCE(".$alias.".recipient_group,'all') IN ('all','teachers'))
            OR (COALESCE(".$alias.".recipient_type,'group')='user' AND ".$alias.".recipient_value='$userIdEsc'))";
    }

    return '1=1';
}
}

if(!function_exists('um_mark_messages_seen')){
function um_mark_messages_seen($con, $userId = ''){
    ensure_user_management_columns($con);
    $userId = trim((string)$userId);
    if($userId === ''){
        $userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    }
    if($userId === ''){
        return false;
    }
    $userIdEsc = mysqli_real_escape_string($con, $userId);
    return @mysqli_query($con, "INSERT INTO tblmessageviewstate(userid,lastseenat,datetimeentry,lastupdatedat)
        VALUES('$userIdEsc',NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE lastseenat=NOW(), lastupdatedat=NOW()");
}
}

if(!function_exists('um_message_unread_count')){
function um_message_unread_count($con, $userId = '', $systemType = ''){
    ensure_user_management_columns($con);
    $userId = trim((string)$userId);
    if($userId === ''){
        $userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    }
    if($userId === ''){
        return 0;
    }
    $systemType = trim((string)$systemType);
    if($systemType === ''){
        $systemType = isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
    }
    $userIdEsc = mysqli_real_escape_string($con, $userId);
    $lastSeen = '';
    $stateRes = @mysqli_query($con, "SELECT lastseenat FROM tblmessageviewstate WHERE userid='$userIdEsc' LIMIT 1");
    if($stateRes && ($stateRow = mysqli_fetch_array($stateRes, MYSQLI_ASSOC))){
        $lastSeen = trim((string)$stateRow['lastseenat']);
    }
    $visibilitySql = um_message_visibility_where_for_user($con, $userId, $systemType, 'mg');
    $timeFilter = '';
    if($lastSeen !== ''){
        $lastSeenEsc = mysqli_real_escape_string($con, $lastSeen);
        $timeFilter = " AND mg.datetimeentry > '$lastSeenEsc'";
    }
    $sql = "SELECT COUNT(*) AS total_unread
        FROM tblmessages mg
        WHERE mg.status='active'
          AND mg.sentby<>'$userIdEsc'
          AND $visibilitySql
          $timeFilter";
    $res = @mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return (int)$row['total_unread'];
    }
    return 0;
}
}

if(!function_exists('um_module_catalog')){
function um_module_catalog(){
    return array(
        'school_setup' => array(
            'label' => 'School Setup',
            'group' => 'Setup',
            'description' => 'School, branch, class, subject, and batch setup.',
            'scripts' => array('company-entry.php','branch-entry.php','batch-entry.php','subject-entry.php','class-entry.php','school-data-entry.php')
        ),
        'student_teacher_registration' => array(
            'label' => 'Student And Teacher Registration',
            'group' => 'People',
            'description' => 'Register and review students and teachers.',
            'scripts' => array('register-student.php','register-teacher.php','upload-register.php','viewusers.php','viewstudents.php')
        ),
        'student_search' => array(
            'label' => 'Student Search',
            'group' => 'People',
            'description' => 'Search student records quickly.',
            'scripts' => array('search.php')
        ),
        'class_semester_registry' => array(
            'label' => 'Class And Semester Registry',
            'group' => 'Academics',
            'description' => 'Class registry and semester registry workflows.',
            'scripts' => array('class-registry.php','upload-class-registry.php','view-class-registry.php','term-registry.php','group-term-registry.php','upload-semester-registry.php')
        ),
        'student_progression' => array(
            'label' => 'Student Progression',
            'group' => 'Academics',
            'description' => 'Promotion center and student transcript tools.',
            'scripts' => array('promotion-center.php','student-history.php','continuing-students.php')
        ),
        'subject_management' => array(
            'label' => 'Subject Management',
            'group' => 'Academics',
            'description' => 'Subject classification, assignment, and views.',
            'scripts' => array('subject-classification.php','view-subject-classified.php','subject-assignment.php','view-all-subject-assigned.php')
        ),
        'class_teacher_assignment' => array(
            'label' => 'Class Teacher Assignment',
            'group' => 'Academics',
            'description' => 'Assign teachers to classes.',
            'scripts' => array('class-teacher-assignment.php')
        ),
        'student_attendance' => array(
            'label' => 'Student Attendance',
            'group' => 'Academics',
            'description' => 'Daily student attendance registers for assigned classes.',
            'scripts' => array('student-attendance.php','student-attendance-report.php')
        ),
        'duty_roster' => array(
            'label' => 'Duty Roster',
            'group' => 'Operations',
            'description' => 'Create and manage staff duty rosters.',
            'scripts' => array('duty-roster.php')
        ),
        'online_admission' => array(
            'label' => 'Online Admission',
            'group' => 'Admissions',
            'description' => 'Manage online admission applications and settings.',
            'scripts' => array('online-admission-admin.php','online-admission-paystack-test.php','online-admission-paystack-test-callback.php')
        ),
        'house_management' => array(
            'label' => 'House Management',
            'group' => 'Operations',
            'description' => 'House, house master, and exeat workflows.',
            'scripts' => array('house-entry.php','house-master-assignment.php','student-house-assignment.php','senior-house-assignment.php','senior-house-dashboard.php','house-master-dashboard.php','house-master-exeat.php')
        ),
        'billing' => array(
            'label' => 'Billing',
            'group' => 'Finance',
            'description' => 'Class fee collection and payment access.',
            'scripts' => array('payments.php','class-billing.php','teacher-billing-assignment.php','student-billing.php','group-student-billing.php','print-student-bills.php','display-class-bill.php')
        ),
        'accounts_finance' => array(
            'label' => 'Accounts And Finance',
            'group' => 'Finance',
            'description' => 'Items, pricing, class billing, and finance reports.',
            'scripts' => array('item-entry.php','item-pricing.php','class-Bills-report.php','daily-report.php','payment-analysis.php','bills-report.php','item-bill-report.php','bills.php','account-statements.php')
        ),
        'examination_scores' => array(
            'label' => 'Examination And Scores',
            'group' => 'Examinations',
            'description' => 'Score entry, uploads, and continuous assessment.',
            'scripts' => array('class-score-entry.php','exam-score-entry.php','upload-class-score-entry.php','upload-exam-score-entry.php','upload-classexam-score.php','all-class-score.php','exam-score.php','class-score.php','continuous-assessment.php')
        ),
        'reports' => array(
            'label' => 'Reports',
            'group' => 'Examinations',
            'description' => 'Result reports, terminal reports, and exam analysis.',
            'scripts' => array('scores-report.php','scores-report-all.php','terminal-report.php','individual-terminal-report.php','student-terminal-data.php','upload-student-remark-data.php','report-approval-board.php','waec-analysis.php','internal-exam-analysis.php','examanalysis-subject.php','examanalysis-rank.php')
        ),
        'examination_timetable' => array(
            'label' => 'Examination Timetable',
            'group' => 'Examinations',
            'description' => 'Timetable entry, templates, and reports.',
            'scripts' => array('examinationtimetable.php','examinationtimetablereport.php','download-classscore-template.php','download-examscore-template.php','download-classexamscore-template.php')
        ),
        'lesson_timetable' => array(
            'label' => 'Lesson Timetable',
            'group' => 'Academics',
            'description' => 'Assign teacher lessons and review the weekly class timetable.',
            'scripts' => array('lesson-timetable.php','lesson-timetable-report.php')
        ),
        'course_registration' => array(
            'label' => 'Course Registration',
            'group' => 'Academics',
            'description' => 'Open semester course registration and review teacher and student course selections.',
            'scripts' => array('course-registration-admin.php','student-course-registration.php','teacher-course-registration.php')
        ),
        'online_voting' => array(
            'label' => 'Online Voting',
            'group' => 'Engagement',
            'description' => 'Run pageantry contests, campaign profiles, and live paid voting.',
            'scripts' => array('online-voting-admin.php','online-voting.php','online-voting-paystack-init.php')
        ),
        'notice_communication' => array(
            'label' => 'Notice And Communication',
            'group' => 'Communication',
            'description' => 'Send notices and work with message center.',
            'scripts' => array('notification.php','messages.php','student-chat.php','student-chat-settings.php','student-chat-monitor.php')
        ),
        'sms_management' => array(
            'label' => 'SMS Management',
            'group' => 'Communication',
            'description' => 'Enable SMS and review SMS logs.',
            'scripts' => array('enablesmsalert.php','smsreport.php','smsreportdata.php')
        ),
        'backup_tools' => array(
            'label' => 'Backup And System Tools',
            'group' => 'System',
            'description' => 'Backups, API keys, and high-risk system utilities.',
            'scripts' => array('backup_db.php','generateapikey.php','global_deletes.php')
        )
    );
}
}

if(!function_exists('um_module_groups')){
function um_module_groups(){
    $catalog = um_module_catalog();
    $groups = array();
    foreach($catalog as $moduleKey => $module){
        $group = isset($module['group']) ? $module['group'] : 'Other';
        if(!isset($groups[$group])){
            $groups[$group] = array();
        }
        $groups[$group][$moduleKey] = $module;
    }
    return $groups;
}
}

if(!function_exists('um_assignable_module_keys_for_role')){
function um_assignable_module_keys_for_role($roleKey){
    $roleKey = trim((string)$roleKey);
    $allKeys = array_keys(um_module_catalog());
    if($roleKey === 'assistant_head_academics'){
        return array(
            'student_search',
            'class_semester_registry',
            'student_progression',
            'subject_management',
            'class_teacher_assignment',
            'student_attendance',
            'examination_scores',
            'reports',
            'examination_timetable',
            'lesson_timetable',
            'course_registration',
            'notice_communication'
        );
    }
    if($roleKey === 'teacher'){
        return array(
            'student_search',
            'class_semester_registry',
            'student_progression',
            'subject_management',
            'class_teacher_assignment',
            'student_attendance',
            'duty_roster',
            'house_management',
            'billing',
            'notice_communication',
            'online_admission',
            'online_voting',
            'student_teacher_registration',
            'examination_timetable',
            'lesson_timetable',
            'course_registration'
        );
    }
    if($roleKey === 'student'){
        return array();
    }
    return $allKeys;
}
}

if(!function_exists('um_default_module_keys_for_role')){
function um_default_module_keys_for_role($roleKey){
    $roleKey = trim((string)$roleKey);
    if($roleKey === 'assistant_head_academics'){
        return um_assignable_module_keys_for_role($roleKey);
    }
    return array();
}
}

if(!function_exists('um_module_groups_for_role')){
function um_module_groups_for_role($roleKey){
    $allowedKeys = array_flip(um_assignable_module_keys_for_role($roleKey));
    $groups = um_module_groups();
    $filtered = array();
    foreach($groups as $groupLabel => $groupModules){
        foreach($groupModules as $moduleKey => $module){
            if(isset($allowedKeys[$moduleKey])){
                if(!isset($filtered[$groupLabel])){
                    $filtered[$groupLabel] = array();
                }
                $filtered[$groupLabel][$moduleKey] = $module;
            }
        }
    }
    return $filtered;
}
}

if(!function_exists('um_normalize_module_keys')){
function um_normalize_module_keys($moduleKeys){
    $catalog = um_module_catalog();
    $normalized = array();
    if(!is_array($moduleKeys)){
        return $normalized;
    }
    foreach($moduleKeys as $moduleKey){
        $moduleKey = trim((string)$moduleKey);
        if($moduleKey !== '' && isset($catalog[$moduleKey])){
            $normalized[$moduleKey] = $moduleKey;
        }
    }
    return array_values($normalized);
}
}

if(!function_exists('um_get_user_module_permissions')){
function um_get_user_module_permissions($con, $userId){
    static $cache = array();
    $userId = trim((string)$userId);
    if($userId === ''){
        return array();
    }
    if(isset($cache[$userId])){
        return $cache[$userId];
    }

    ensure_user_management_columns($con);
    $permissions = array();
    $stmt = @mysqli_prepare($con, "SELECT modulekey FROM tblusermodulepermission WHERE userid=? AND status='active' ORDER BY modulekey ASC");
    if($stmt){
        mysqli_stmt_bind_param($stmt, 's', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if($result){
            while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                $moduleKey = trim((string)$row['modulekey']);
                if($moduleKey !== ''){
                    $permissions[$moduleKey] = $moduleKey;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
    $cache[$userId] = array_values($permissions);
    return $cache[$userId];
}
}

if(!function_exists('um_teacher_extra_nav_links')){
function um_teacher_extra_nav_links($con, $userId = ''){
    if($userId === ''){
        $userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    }
    if($userId === ''){
        return array();
    }

    $userRow = um_fetch_user_row($con, $userId);
    if(!$userRow || um_role_key_from_user($userRow) !== 'teacher'){
        return array();
    }
    if(!um_user_has_custom_permissions($con, $userId)){
        return array();
    }

    $permissions = um_get_user_module_permissions($con, $userId);
    $links = array();
    $map = array(
        'student_search' => array(
            array('href' => 'search.php', 'label' => 'Search Student', 'icon' => 'fa-search')
        ),
        'class_semester_registry' => array(
            array('href' => 'class-registry.php', 'label' => 'Class Registry', 'icon' => 'fa-folder-open'),
            array('href' => 'term-registry.php', 'label' => 'Semester Registry', 'icon' => 'fa-calendar')
        ),
        'student_progression' => array(
            array('href' => 'promotion-center.php', 'label' => 'Promotion Center', 'icon' => 'fa-level-up'),
            array('href' => 'student-history.php', 'label' => 'Student Transcript', 'icon' => 'fa-history')
        ),
        'subject_management' => array(
            array('href' => 'subject-classification.php', 'label' => 'Subject Classification', 'icon' => 'fa-book'),
            array('href' => 'subject-assignment.php', 'label' => 'Subject Assignment', 'icon' => 'fa-plus'),
            array('href' => 'view-all-subject-assigned.php', 'label' => 'View Subject(s) Assigned', 'icon' => 'fa-book')
        ),
        'class_teacher_assignment' => array(
            array('href' => 'class-teacher-assignment.php', 'label' => 'Class Teacher Assignment', 'icon' => 'fa-users')
        ),
        'duty_roster' => array(
            array('href' => 'duty-roster.php', 'label' => 'Duty Roster', 'icon' => 'fa-calendar-check-o')
        ),
        'house_management' => array(
            array('href' => 'house-entry.php', 'label' => 'House Entry', 'icon' => 'fa-home'),
            array('href' => 'student-house-assignment.php', 'label' => 'Student House Assignment', 'icon' => 'fa-users')
        ),
        'billing' => array(
            array('href' => 'payments.php', 'label' => 'Class Payments', 'icon' => 'fa-credit-card')
        ),
        'notice_communication' => array(
            array('href' => 'notification.php', 'label' => 'Send Notification', 'icon' => 'fa-bullhorn'),
            array('href' => 'student-chat-monitor.php', 'label' => 'Student Chat Monitor', 'icon' => 'fa-eye'),
            array('href' => 'student-chat-settings.php', 'label' => 'Student Chat Control', 'icon' => 'fa-sliders')
        ),
        'online_admission' => array(
            array('href' => 'online-admission-admin.php', 'label' => 'Online Admission', 'icon' => 'fa-globe')
        ),
        'student_teacher_registration' => array(
            array('href' => 'register-student.php', 'label' => 'Register Student', 'icon' => 'fa-user'),
            array('href' => 'register-teacher.php', 'label' => 'Register Teacher', 'icon' => 'fa-user-plus'),
            array('href' => 'viewstudents.php', 'label' => 'View Students', 'icon' => 'fa-graduation-cap'),
            array('href' => 'viewusers.php', 'label' => 'View Teachers', 'icon' => 'fa-users')
        ),
        'examination_timetable' => array(
            array('href' => 'examinationtimetable.php', 'label' => 'Exam Time Table Entry', 'icon' => 'fa-calendar')
        ),
        'lesson_timetable' => array(
            array('href' => 'lesson-timetable.php', 'label' => 'Lesson Timetable Entry', 'icon' => 'fa-calendar')
        ),
        'course_registration' => array(
            array('href' => 'course-registration-admin.php', 'label' => 'Course Registration', 'icon' => 'fa-list-alt')
        )
    );

    foreach($permissions as $moduleKey){
        if(isset($map[$moduleKey])){
            foreach($map[$moduleKey] as $link){
                $links[$link['href']] = $link;
            }
        }
    }

    return array_values($links);
}
}

if(!function_exists('um_user_has_custom_permissions')){
function um_user_has_custom_permissions($con, $userId){
    $permissions = um_get_user_module_permissions($con, $userId);
    if(!empty($permissions)){
        return true;
    }
    $userRow = um_fetch_user_row($con, $userId);
    return $userRow && isset($userRow['module_permission_mode']) && trim((string)$userRow['module_permission_mode']) === 'custom';
}
}

if(!function_exists('um_save_user_module_permissions')){
function um_save_user_module_permissions($con, $userId, $moduleKeys, $recordedBy = ''){
    $userId = trim((string)$userId);
    if($userId === ''){
        return false;
    }

    ensure_user_management_columns($con);
    $moduleKeys = um_normalize_module_keys($moduleKeys);
    $recordedBy = trim((string)$recordedBy);

    @mysqli_query($con, "DELETE FROM tblusermodulepermission WHERE userid='".mysqli_real_escape_string($con, $userId)."'");
    foreach($moduleKeys as $moduleKey){
        $stmt = @mysqli_prepare($con, "INSERT INTO tblusermodulepermission (userid,modulekey,recordedby,status,datetimeentry) VALUES (?,?,?,'active',NOW())");
        if($stmt){
            mysqli_stmt_bind_param($stmt, 'sss', $userId, $moduleKey, $recordedBy);
            @mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    return true;
}
}

if(!function_exists('um_home_link_for_session')){
function um_home_link_for_session(){
    if(!isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'])){
        return 'index.php';
    }
    if($_SESSION['ACCESSLEVEL'] === 'administrator' && $_SESSION['SYSTEMTYPE'] === 'super_user'){
        return 'super.php';
    }
    if($_SESSION['ACCESSLEVEL'] === 'administrator' && $_SESSION['SYSTEMTYPE'] === 'normal_user'){
        return 'admin.php';
    }
    if($_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'Teacher'){
        return 'teacher-page.php';
    }
    if($_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'Student'){
        return 'student-page.php';
    }
    if($_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'Headmaster'){
        return 'headmaster-page.php';
    }
    if($_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'AssistantHeadAcademic'){
        return 'assistant-head-academics-page.php';
    }
    if($_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'User'){
        return 'user.php';
    }
    return 'index.php';
}
}

if(!function_exists('um_baseline_scripts_for_role')){
function um_baseline_scripts_for_role($roleKey){
    $roleKey = trim((string)$roleKey);
    if($roleKey === 'teacher'){
        return array(
            'teacher-page.php',
            'messages.php',
            'view-teacher-subject.php',
            'view-subject-assigned.php',
            'student-attendance.php',
            'student-attendance-report.php',
            'download-classscore-template.php',
            'download-examscore-template.php',
            'download-classexamscore-template.php',
            'lesson-timetable-report.php',
            'online-class.php',
            'teacher-course-registration.php',
            'online-voting.php',
            'online-voting-paystack-init.php',
            'class-score-entry.php',
            'exam-score-entry.php',
            'upload-class-score-entry.php',
            'upload-exam-score-entry.php',
            'upload-classexam-score.php',
            'scores-report.php',
            'student-terminal-data.php',
            'upload-student-remark-data.php',
            'terminal-report.php',
            'examinationtimetablereport.php',
            'house-master-dashboard.php',
            'house-master-exeat.php',
            'senior-house-dashboard.php'
        );
    }
    if($roleKey === 'student'){
        return array(
            'individual-terminal-report.php',
            'account-statements.php',
            'examinationtimetablereport.php',
            'messages.php',
            'student-chat.php',
            'student-attendance-report.php',
            'lesson-timetable-report.php',
            'online-class.php',
            'student-course-registration.php',
            'online-voting.php',
            'online-voting-paystack-init.php'
        );
    }
    if($roleKey === 'headmaster'){
        return array(
            'headmaster-page.php',
            'search.php',
            'viewstudents.php',
            'viewusers.php',
            'duty-roster.php',
            'senior-house-dashboard.php',
            'messages.php',
            'student-history.php',
            'continuing-students.php',
            'view-class-registry.php',
            'student-attendance-report.php',
            'terminal-report.php',
            'internal-exam-analysis.php',
            'waec-analysis.php',
            'lesson-timetable-report.php',
            'examinationtimetablereport.php',
            'daily-report.php',
            'payment-analysis.php',
            'bills-report.php',
            'item-bill-report.php',
            'online-admission-admin.php',
            'notification.php'
        );
    }
    if($roleKey === 'assistant_head_academics'){
        return array(
            'assistant-head-academics-page.php'
        );
    }
    return array();
}
}

if(!function_exists('um_module_key_for_script')){
function um_module_key_for_script($scriptName){
    $scriptName = trim((string)$scriptName);
    if($scriptName === ''){
        return null;
    }
    $catalog = um_module_catalog();
    foreach($catalog as $moduleKey => $module){
        if(!empty($module['scripts']) && in_array($scriptName, $module['scripts'], true)){
            return $moduleKey;
        }
    }
    return null;
}
}

if(!function_exists('um_user_has_module')){
function um_user_has_module($con, $moduleKey, $userRow = null){
    $moduleKey = trim((string)$moduleKey);
    if($moduleKey === ''){
        return true;
    }

    $catalog = um_module_catalog();
    if(!isset($catalog[$moduleKey])){
        return true;
    }

    if($userRow === null){
        $userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
        if($userId === ''){
            return false;
        }
        $userRow = um_fetch_user_row($con, $userId);
    }
    if(!$userRow){
        return false;
    }

    $roleKey = um_role_key_from_user($userRow);
    if($roleKey === 'system_admin'){
        return true;
    }

    $userId = trim((string)$userRow['userid']);
    $permissions = um_get_user_module_permissions($con, $userId);
    $permissionMode = isset($userRow['module_permission_mode']) ? trim((string)$userRow['module_permission_mode']) : 'legacy';
    if(empty($permissions)){
        if($permissionMode === 'custom'){
            return false;
        }
        if(in_array($roleKey, array('teacher', 'student'), true)){
            return false;
        }
        return true;
    }

    return in_array($moduleKey, $permissions, true);
}
}

if(!function_exists('um_current_user_can_access_module')){
function um_current_user_can_access_module($con, $moduleKey){
    $userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    if($userId === ''){
        return false;
    }
    $userRow = um_fetch_user_row($con, $userId);
    if(!$userRow){
        return false;
    }
    return um_user_has_module($con, $moduleKey, $userRow);
}
}

if(!function_exists('um_enforce_current_module_access')){
function um_enforce_current_module_access($con){
    if(!isset($_SESSION['USERID'])){
        return;
    }

    $scriptName = basename((string)(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''));
    $skipScripts = array(
        'index.php','logout.php','change-password.php','edit-account.php','uploaduser-image.php',
        'select-branch.php','student-page.php','teacher-page.php','admin.php','super.php','user.php','headmaster-page.php'
    );
    if(in_array($scriptName, $skipScripts, true)){
        return;
    }

    $moduleKey = um_module_key_for_script($scriptName);
    if($moduleKey === null){
        return;
    }

    $userRow = um_fetch_user_row($con, isset($_SESSION['USERID']) ? $_SESSION['USERID'] : '');
    if(!$userRow){
        return;
    }

    $roleKey = um_role_key_from_user($userRow);
    if(in_array($scriptName, um_baseline_scripts_for_role($roleKey), true)){
        return;
    }

    if(!um_user_has_module($con, $moduleKey, $userRow)){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;padding:8px;'>You do not have access to that module.</div>";
        header("location:".um_home_link_for_session());
        exit();
    }
}
}

if(!function_exists('um_is_admin_manager')){
function um_is_admin_manager(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'administrator' &&
        in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true);
}
}

if(!function_exists('um_is_super_admin_manager')){
function um_is_super_admin_manager(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'administrator' &&
        $_SESSION['SYSTEMTYPE'] === 'super_user';
}
}

if(!function_exists('um_is_headmaster_user')){
function um_is_headmaster_user(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'Headmaster';
}
}

if(!function_exists('um_is_assistant_head_academics_user')){
function um_is_assistant_head_academics_user(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'AssistantHeadAcademic';
}
}

if(!function_exists('um_is_academic_lead_user')){
function um_is_academic_lead_user(){
    return (function_exists('um_is_headmaster_user') && um_is_headmaster_user()) ||
        (function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user());
}
}

if(!function_exists('um_role_profiles')){
function um_role_profiles(){
    return array(
        'office_user' => array(
            'label' => 'Office User',
            'accesslevel' => 'user',
            'systemtype' => 'User',
            'description' => 'General office or support account.',
            'super_only' => false
        ),
        'headmaster' => array(
            'label' => 'Headmaster',
            'accesslevel' => 'user',
            'systemtype' => 'Headmaster',
            'description' => 'Executive school leadership dashboard account.',
            'super_only' => false
        ),
        'assistant_head_academics' => array(
            'label' => 'Assistant Head Academics',
            'accesslevel' => 'user',
            'systemtype' => 'AssistantHeadAcademic',
            'description' => 'Academic leadership dashboard account.',
            'super_only' => false
        ),
        'branch_admin' => array(
            'label' => 'Branch Administrator',
            'accesslevel' => 'administrator',
            'systemtype' => 'normal_user',
            'description' => 'Administrative access for one branch.',
            'super_only' => true
        ),
        'system_admin' => array(
            'label' => 'System Administrator',
            'accesslevel' => 'administrator',
            'systemtype' => 'super_user',
            'description' => 'Full system-wide administrative access.',
            'super_only' => true
        )
    );
}
}

if(!function_exists('um_available_creation_roles')){
function um_available_creation_roles(){
    $profiles = um_role_profiles();
    if(um_is_super_admin_manager()){
        return $profiles;
    }

    $allowed = array();
    foreach($profiles as $key => $profile){
        if(empty($profile['super_only'])){
            $allowed[$key] = $profile;
        }
    }
    return $allowed;
}
}

if(!function_exists('um_role_key_from_user')){
function um_role_key_from_user($row){
    $accessLevel = isset($row['accesslevel']) ? trim((string)$row['accesslevel']) : '';
    $systemType = isset($row['systemtype']) ? trim((string)$row['systemtype']) : '';

    if($accessLevel === 'administrator' && $systemType === 'super_user'){
        return 'system_admin';
    }
    if($accessLevel === 'administrator' && $systemType === 'normal_user'){
        return 'branch_admin';
    }
    if($accessLevel === 'user' && $systemType === 'User'){
        return 'office_user';
    }
    if($accessLevel === 'user' && $systemType === 'Headmaster'){
        return 'headmaster';
    }
    if($accessLevel === 'user' && $systemType === 'AssistantHeadAcademic'){
        return 'assistant_head_academics';
    }
    if($systemType === 'Teacher'){
        return 'teacher';
    }
    if($systemType === 'Student'){
        return 'student';
    }
    return 'unknown';
}
}

if(!function_exists('um_role_label_from_user')){
function um_role_label_from_user($row){
    $roleKey = um_role_key_from_user($row);
    $profiles = um_role_profiles();
    if(isset($profiles[$roleKey])){
        return $profiles[$roleKey]['label'];
    }
    if($roleKey === 'teacher'){
        return 'Teacher';
    }
    if($roleKey === 'student'){
        return 'Student';
    }
    return trim((string)(isset($row['systemtype']) ? $row['systemtype'] : 'User'));
}
}

if(!function_exists('um_generate_userid')){
function um_generate_userid($roleKey = 'office_user'){
    $prefix = 'USR';
    if($roleKey === 'headmaster'){
        $prefix = 'HDM';
    }elseif($roleKey === 'assistant_head_academics'){
        $prefix = 'AHA';
    }elseif($roleKey === 'branch_admin'){
        $prefix = 'ADM';
    }elseif($roleKey === 'system_admin'){
        $prefix = 'SUP';
    }
    return $prefix.date('ymdHis').mt_rand(10, 99);
}
}

if(!function_exists('um_generate_temp_password')){
function um_generate_temp_password($length = 8){
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $length = max(6, (int)$length);
    $password = '';
    for($i = 0; $i < $length; $i++){
        $password .= $alphabet[random_int(0, $max)];
    }
    return $password;
}
}

if(!function_exists('um_age_from_birthdate')){
function um_age_from_birthdate($birthdate){
    $birthdate = trim((string)$birthdate);
    if($birthdate === ''){
        return 0;
    }
    try{
        $birth = new DateTime($birthdate);
        $today = new DateTime('today');
        return max(0, (int)$birth->diff($today)->y);
    }catch(Exception $e){
        return 0;
    }
}
}

if(!function_exists('um_can_manage_row')){
function um_can_manage_row($row){
    if(!um_is_admin_manager()){
        return false;
    }
    if(um_is_super_admin_manager()){
        return true;
    }

    $currentBranch = isset($_SESSION['BRANCHID']) ? (string)$_SESSION['BRANCHID'] : '';
    $rowBranch = isset($row['branchid']) ? (string)$row['branchid'] : '';
    if($currentBranch !== '' && $rowBranch !== '' && $currentBranch !== $rowBranch){
        return false;
    }
    if(isset($row['systemtype']) && trim((string)$row['systemtype']) === 'super_user'){
        return false;
    }
    if(isset($row['accesslevel']) && trim((string)$row['accesslevel']) === 'administrator'){
        return false;
    }
    return true;
}
}

if(!function_exists('um_fetch_user_row')){
function um_fetch_user_row($con, $userId){
    $stmt = @mysqli_prepare($con, "SELECT * FROM tblsystemuser WHERE userid=? LIMIT 1");
    if(!$stmt){
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_array($result, MYSQLI_ASSOC) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}
}

if(!function_exists('um_requires_password_change')){
function um_requires_password_change($row){
    return (int)(isset($row['password_reset_required']) ? $row['password_reset_required'] : 0) === 1;
}
}

if(!function_exists('um_delete_or_block_user')){
function um_delete_or_block_user($con, $userId){
    $userId = trim((string)$userId);
    if($userId === ''){
        return array('status' => 'failed', 'message' => 'User ID is required.');
    }

    $target = um_fetch_user_row($con, $userId);
    if(!$target){
        return array('status' => 'failed', 'message' => 'User account was not found.');
    }
    if(!um_can_manage_row($target)){
        return array('status' => 'failed', 'message' => 'You cannot manage that user account.');
    }
    if(isset($_SESSION['USERID']) && $_SESSION['USERID'] === $userId){
        return array('status' => 'failed', 'message' => 'You cannot delete your own active account.');
    }

    if(trim((string)$target['systemtype']) === 'super_user' && trim((string)$target['status']) === 'active'){
        $result = @mysqli_query($con, "SELECT COUNT(*) AS total_active FROM tblsystemuser WHERE systemtype='super_user' AND status='active'");
        $countActive = 0;
        if($result && $row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $countActive = (int)$row['total_active'];
        }
        if($countActive <= 1){
            return array('status' => 'failed', 'message' => 'The last active system administrator cannot be deleted or blocked.');
        }
    }

    $stmtDelete = @mysqli_prepare($con, "DELETE FROM tblsystemuser WHERE userid=? LIMIT 1");
    if($stmtDelete){
        mysqli_stmt_bind_param($stmtDelete, 's', $userId);
        $okDelete = @mysqli_stmt_execute($stmtDelete);
        $deleteErrNo = mysqli_errno($con);
        mysqli_stmt_close($stmtDelete);
        if($okDelete && mysqli_affected_rows($con) > 0){
            return array('status' => 'deleted', 'message' => 'User account deleted.');
        }
        if($deleteErrNo === 1451){
            $stmtBlock = @mysqli_prepare($con, "UPDATE tblsystemuser SET status='block' WHERE userid=? LIMIT 1");
            if($stmtBlock){
                mysqli_stmt_bind_param($stmtBlock, 's', $userId);
                $okBlock = @mysqli_stmt_execute($stmtBlock);
                mysqli_stmt_close($stmtBlock);
                if($okBlock){
                    return array('status' => 'blocked', 'message' => 'User has linked records and was blocked instead of deleted.');
                }
            }
        }
    }

    return array('status' => 'failed', 'message' => 'User account could not be deleted.');
}
}
