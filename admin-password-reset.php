<?php
session_start();
$_SESSION['Message'] = "";

include("dbstring.php");
include("audit_notifications.php");
include_once("house-master-utils.php");
include_once("user-management-utils.php");
ensure_user_management_columns($con);

if (!function_exists('ensureAdminPasswordResetSmsLogTable')) {
    function ensureAdminPasswordResetSmsLogTable($con)
    {
        static $done = false;
        if ($done || !$con) {
            return;
        }
        $done = true;
        @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tbladminpasswordresetsmslog (
            logid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            target_userid VARCHAR(60) NOT NULL,
            target_type VARCHAR(30) NOT NULL,
            mobile VARCHAR(40) DEFAULT '',
            sms_message VARCHAR(255) DEFAULT '',
            sms_status VARCHAR(30) NOT NULL,
            sms_code VARCHAR(80) DEFAULT '',
            admin_userid VARCHAR(60) DEFAULT '',
            datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (logid),
            KEY idx_target_userid (target_userid),
            KEY idx_sms_status (sms_status),
            KEY idx_datetimeentry (datetimeentry)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('logAdminPasswordResetSmsOutcome')) {
    function logAdminPasswordResetSmsOutcome($con, $targetUserId, $targetType, $mobile, $smsMessage, $smsStatus, $smsCode = '')
    {
        if (!$con) {
            return false;
        }

        ensureAdminPasswordResetSmsLogTable($con);

        $adminUserId = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '';
        $targetUserId = (string)$targetUserId;
        $targetType = (string)$targetType;
        $mobile = (string)$mobile;
        $smsMessage = (string)$smsMessage;
        $smsStatus = (string)$smsStatus;
        $smsCode = (string)$smsCode;

        $stmt = @mysqli_prepare($con, "INSERT INTO tbladminpasswordresetsmslog
            (target_userid,target_type,mobile,sms_message,sms_status,sms_code,admin_userid,datetimeentry)
            VALUES (?,?,?,?,?,?,?,NOW())");
        if (!$stmt) {
            return false;
        }

        @mysqli_stmt_bind_param($stmt, "sssssss", $targetUserId, $targetType, $mobile, $smsMessage, $smsStatus, $smsCode, $adminUserId);
        $ok = @mysqli_stmt_execute($stmt);
        @mysqli_stmt_close($stmt);
        return $ok ? true : false;
    }
}

if (!function_exists('adminPasswordResetNormalizePhone')) {
    function adminPasswordResetNormalizePhone($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        return preg_replace('/\s+/', '', $value);
    }
}

if (!function_exists('adminPasswordResetFullName')) {
    function adminPasswordResetFullName($row)
    {
        return trim(
            (string)(isset($row['firstname']) ? $row['firstname'] : '') . ' ' .
            (string)(isset($row['othernames']) ? $row['othernames'] : '') . ' ' .
            (string)(isset($row['surname']) ? $row['surname'] : '')
        );
    }
}

if (!function_exists('adminPasswordResetResolveSmsTarget')) {
    function adminPasswordResetResolveSmsTarget($row, $targetType)
    {
        $targetType = trim((string)$targetType);
        $mobile = adminPasswordResetNormalizePhone(isset($row['mobile']) ? $row['mobile'] : '');
        $nextOfKin = adminPasswordResetNormalizePhone(isset($row['nextofkin_contact']) ? $row['nextofkin_contact'] : '');

        if ($targetType === 'Teacher') {
            return array(
                'phone' => $mobile,
                'label' => 'Teacher Mobile',
                'source' => 'teacher_mobile',
            );
        }

        if ($mobile !== '') {
            return array(
                'phone' => $mobile,
                'label' => 'Student/Parent Mobile',
                'source' => 'student_mobile',
            );
        }

        if ($nextOfKin !== '') {
            return array(
                'phone' => $nextOfKin,
                'label' => 'Guardian Contact',
                'source' => 'next_of_kin',
            );
        }

        return array(
            'phone' => '',
            'label' => 'No Contact',
            'source' => 'none',
        );
    }
}

if (!function_exists('adminPasswordResetBuildStudentSmsMessage')) {
    function adminPasswordResetBuildStudentSmsMessage($row, $username, $passwordRaw)
    {
        $studentName = adminPasswordResetFullName($row);
        if ($studentName === '') {
            $studentName = trim((string)(isset($row['userid']) ? $row['userid'] : 'Student'));
        }

        $guardianName = trim((string)(isset($row['nextofkin_fullname']) ? $row['nextofkin_fullname'] : ''));
        if ($guardianName !== '') {
            return "Hello " . $guardianName . ". Student portal login for " . $studentName . ". Username: " . $username . ". Password: " . $passwordRaw . ". Please log in and change password immediately.";
        }

        return "Student portal login for " . $studentName . ". Username: " . $username . ". Password: " . $passwordRaw . ". Please log in and change password immediately.";
    }
}

if (!function_exists('adminPasswordResetBuildRedirectUrl')) {
    function adminPasswordResetBuildRedirectUrl($targetType, $keyword, $selectedBatchId = '', $selectedClassId = '', $page = 1, $perPage = 100)
    {
        $params = array(
            'type' => trim((string)$targetType) !== '' ? trim((string)$targetType) : 'Student',
        );

        $keyword = trim((string)$keyword);
        if ($keyword !== '') {
            $params['q'] = $keyword;
        }

        if ((string)$params['type'] === 'Student') {
            $selectedBatchId = trim((string)$selectedBatchId);
            $selectedClassId = trim((string)$selectedClassId);
            if ($selectedBatchId !== '') {
                $params['scope_batchid'] = $selectedBatchId;
            }
            if ($selectedClassId !== '') {
                $params['scope_classid'] = $selectedClassId;
            }
        }

        $page = (int)$page;
        if ($page > 1) {
            $params['page'] = $page;
        }

        $perPage = (int)$perPage;
        if ($perPage > 0) {
            $params['per_page'] = $perPage;
        }

        return 'admin-password-reset.php?' . http_build_query($params);
    }
}

if (!function_exists('adminPasswordResetNormalizePerPage')) {
    function adminPasswordResetNormalizePerPage($value)
    {
        $value = (int)$value;
        $allowed = array(50, 100, 250, 500);
        if (!in_array($value, $allowed, true)) {
            return 100;
        }
        return $value;
    }
}

if (!function_exists('adminPasswordResetBuildScopeLabel')) {
    function adminPasswordResetBuildScopeLabel($batchLabel, $classLabel)
    {
        $batchLabel = trim((string)$batchLabel);
        $classLabel = trim((string)$classLabel);

        if ($batchLabel !== '' && $classLabel !== '') {
            return $batchLabel . ' / ' . $classLabel;
        }
        if ($batchLabel !== '') {
            return $batchLabel;
        }
        if ($classLabel !== '') {
            return $classLabel;
        }
        return 'No bulk scope selected';
    }
}

if (!function_exists('adminPasswordResetGenerateTempPassword')) {
    function adminPasswordResetGenerateTempPassword($length = 8)
    {
        $length = (int)$length;
        if ($length < 6) {
            $length = 6;
        }

        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $maxIndex = strlen($chars) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            try {
                $index = random_int(0, $maxIndex);
            } catch (Exception $e) {
                $index = mt_rand(0, $maxIndex);
            }
            $password .= $chars[$index];
        }

        return $password;
    }
}

if (!function_exists('adminPasswordResetApplyCredentialReset')) {
    function adminPasswordResetApplyCredentialReset($con, $userRow, $resetType, $newUsername, $newPasswordRaw)
    {
        $result = array(
            'ok' => false,
            'message' => 'Reset failed. Please try again.',
            'sms_status' => '',
            'sms_note' => '',
            'target_userid' => trim((string)(isset($userRow['userid']) ? $userRow['userid'] : '')),
        );

        $targetUserId = trim((string)(isset($userRow['userid']) ? $userRow['userid'] : ''));
        $resetType = trim((string)$resetType);
        $newUsername = trim((string)$newUsername);
        $newPasswordRaw = trim((string)$newPasswordRaw);

        if ($targetUserId === '' || $newUsername === '' || $newPasswordRaw === '') {
            $result['message'] = 'User, username and password are required.';
            return $result;
        }

        $newPassword = md5($newPasswordRaw);
        $stmtUpdate = mysqli_prepare($con, "UPDATE tblsystemuser
            SET username=?, password=?, password_reset_required=1, password_last_reset_at=NOW()
            WHERE userid=? AND systemtype=? LIMIT 1");
        if (!$stmtUpdate) {
            $result['message'] = 'Reset failed. Could not prepare update query.';
            return $result;
        }

        mysqli_stmt_bind_param($stmtUpdate, "ssss", $newUsername, $newPassword, $targetUserId, $resetType);
        $okUpdate = mysqli_stmt_execute($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);

        if (!$okUpdate) {
            return $result;
        }

        $fullName = adminPasswordResetFullName($userRow);
        logSystemChange(
            $con,
            "ADMIN_PASSWORD_RESET",
            "Admin reset password for " . $resetType . " user " . $fullName . " (" . $targetUserId . ").",
            $targetUserId
        );

        $result['ok'] = true;
        $result['message'] = 'Password reset successful.';

        if ($resetType === "Teacher") {
            $teacherPhone = trim((string)(isset($userRow['mobile']) ? $userRow['mobile'] : ''));
            $teacherName = adminPasswordResetFullName($userRow);
            $smsMessage = "Hello " . $teacherName . ", your account password was reset by school admin. Username: " . $newUsername . ". Please login and change password immediately.";
            if ($teacherPhone !== "") {
                $smsCode = "";
                $smsSent = send_bulk_sms_message($teacherPhone, $smsMessage, $smsCode);
                if ($smsSent) {
                    logAdminPasswordResetSmsOutcome($con, $targetUserId, $resetType, $teacherPhone, $smsMessage, "SENT", (string)$smsCode);
                    $result['sms_status'] = 'SENT';
                    $result['sms_note'] = "Teacher SMS notification sent.";
                } else {
                    logAdminPasswordResetSmsOutcome($con, $targetUserId, $resetType, $teacherPhone, $smsMessage, "FAILED", (string)$smsCode);
                    $result['sms_status'] = 'FAILED';
                    $result['sms_note'] = "Password reset completed, but teacher SMS failed (code: " . htmlspecialchars((string)$smsCode) . ").";
                }
            } else {
                logAdminPasswordResetSmsOutcome($con, $targetUserId, $resetType, "", $smsMessage, "NO_PHONE", "NO_PHONE");
                $result['sms_status'] = 'NO_PHONE';
                $result['sms_note'] = "Password reset completed, but no teacher phone number is available.";
            }
            return $result;
        }

        if ($resetType === "Student") {
            $smsTarget = adminPasswordResetResolveSmsTarget($userRow, $resetType);
            $studentSmsPhone = $smsTarget['phone'];
            $studentSmsLabel = $smsTarget['label'];
            $smsMessage = adminPasswordResetBuildStudentSmsMessage($userRow, $newUsername, $newPasswordRaw);
            $smsLogMessage = str_replace($newPasswordRaw, "[REDACTED]", $smsMessage);

            if ($studentSmsPhone !== "") {
                $smsCode = "";
                $smsSent = send_bulk_sms_message($studentSmsPhone, $smsMessage, $smsCode);
                if ($smsSent) {
                    logAdminPasswordResetSmsOutcome($con, $targetUserId, $resetType, $studentSmsPhone, $smsLogMessage, "SENT", (string)$smsCode);
                    $result['sms_status'] = 'SENT';
                    $result['sms_note'] = "Student credentials were sent to the registered contact (" . htmlspecialchars($studentSmsLabel) . ").";
                } else {
                    logAdminPasswordResetSmsOutcome($con, $targetUserId, $resetType, $studentSmsPhone, $smsLogMessage, "FAILED", (string)$smsCode);
                    $result['sms_status'] = 'FAILED';
                    $result['sms_note'] = "Password reset completed, but the student credential SMS failed (code: " . htmlspecialchars((string)$smsCode) . ").";
                }
            } else {
                logAdminPasswordResetSmsOutcome($con, $targetUserId, $resetType, "", $smsLogMessage, "NO_CONTACT", "NO_CONTACT");
                $result['sms_status'] = 'NO_CONTACT';
                $result['sms_note'] = "Password reset completed, but no student or guardian phone number is available.";
            }
        }

        return $result;
    }
}

if (!function_exists('apr_esc')) {
    function apr_esc($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adminPasswordResetFlashHtml')) {
    function adminPasswordResetFlashHtml($tone, $message)
    {
        $tone = trim((string)$tone);
        $allowed = array('success', 'error', 'warning', 'info');
        if (!in_array($tone, $allowed, true)) {
            $tone = 'info';
        }

        $icon = 'fa-info-circle';
        if ($tone === 'success') {
            $icon = 'fa-check-circle';
        } elseif ($tone === 'error') {
            $icon = 'fa-exclamation-circle';
        } elseif ($tone === 'warning') {
            $icon = 'fa-exclamation-triangle';
        }

        return "<div class='apr-flash apr-flash--" . $tone . "'><span class='apr-flash__icon'><i class='fa " . $icon . "'></i></span><div class='apr-flash__body'>" . $message . "</div></div>";
    }
}

$isAdmin = isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
    $_SESSION['ACCESSLEVEL'] === "administrator" &&
    in_array($_SESSION['SYSTEMTYPE'], array("normal_user", "super_user"), true);

if (!$isAdmin) {
    header("location:index.php");
    exit();
}

$targetType = isset($_GET['type']) ? trim($_GET['type']) : "Student";
if ($targetType !== "Teacher" && $targetType !== "Student") {
    $targetType = "Student";
}

$keyword = isset($_REQUEST['q']) ? trim((string)$_REQUEST['q']) : "";
$selectedBatchId = isset($_REQUEST['scope_batchid']) ? trim((string)$_REQUEST['scope_batchid']) : "";
$selectedClassId = isset($_REQUEST['scope_classid']) ? trim((string)$_REQUEST['scope_classid']) : "";
$currentPage = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}
$perPage = adminPasswordResetNormalizePerPage(isset($_REQUEST['per_page']) ? $_REQUEST['per_page'] : 100);

$batchOptions = array();
$classOptions = array();
$selectedBatchLabel = "";
$selectedClassLabel = "";

if ($targetType === "Student") {
    $batchQuery = mysqli_query($con, "SELECT batchid, batch FROM tblbatch ORDER BY datetimeentry DESC");
    if ($batchQuery) {
        while ($row = mysqli_fetch_array($batchQuery, MYSQLI_ASSOC)) {
            $batchOptions[] = $row;
            if ($selectedBatchId !== "" && $selectedBatchId === trim((string)$row['batchid'])) {
                $selectedBatchLabel = trim((string)$row['batch']);
            }
        }
    }

    $classQuery = mysqli_query($con, "SELECT class_entryid, class_name FROM tblclassentry ORDER BY class_name ASC");
    if ($classQuery) {
        while ($row = mysqli_fetch_array($classQuery, MYSQLI_ASSOC)) {
            $classOptions[] = $row;
            if ($selectedClassId !== "" && $selectedClassId === trim((string)$row['class_entryid'])) {
                $selectedClassLabel = trim((string)$row['class_name']);
            }
        }
    }
}

if (isset($_POST['admin_bulk_reset_password'])) {
    $resetType = isset($_POST['target_type']) ? trim((string)$_POST['target_type']) : "Student";
    $selectedBatchId = isset($_POST['scope_batchid']) ? trim((string)$_POST['scope_batchid']) : $selectedBatchId;
    $selectedClassId = isset($_POST['scope_classid']) ? trim((string)$_POST['scope_classid']) : $selectedClassId;
    $currentPage = isset($_POST['page']) ? (int)$_POST['page'] : $currentPage;
    if ($currentPage < 1) {
        $currentPage = 1;
    }
    $perPage = adminPasswordResetNormalizePerPage(isset($_POST['per_page']) ? $_POST['per_page'] : $perPage);

    if ($resetType !== "Student") {
        $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'Bulk credential sending is available for students only.');
    } elseif ($selectedBatchId === "" && $selectedClassId === "") {
        $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'Select a batch, class, or both before running a bulk credential send.');
    } else {
        $batchSafe = mysqli_real_escape_string($con, $selectedBatchId);
        $classSafe = mysqli_real_escape_string($con, $selectedClassId);

        $scopeSql = "EXISTS (
            SELECT 1
            FROM tblclass cl
            WHERE cl.userid=su.userid
              AND cl.status='active'";
        if ($selectedBatchId !== '') {
            $scopeSql .= " AND cl.batchid='" . $batchSafe . "'";
        }
        if ($selectedClassId !== '') {
            $scopeSql .= " AND cl.class_entryid='" . $classSafe . "'";
        }
        $scopeSql .= ")";

        $sqlBulk = "SELECT su.userid,su.firstname,su.othernames,su.surname,su.username,su.mobile,su.nextofkin_fullname,su.nextofkin_contact,su.relationship
            FROM tblsystemuser su
            WHERE su.systemtype='Student'
              AND su.status='active'
              AND " . $scopeSql . "
            ORDER BY su.firstname ASC,su.surname ASC,su.othernames ASC";
        $bulkRes = mysqli_query($con, $sqlBulk);

        if (!$bulkRes) {
            $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'Bulk credential lookup failed. Please try again.');
        } else {
            $total = 0;
            $resetSuccess = 0;
            $resetFailed = 0;
            $smsSent = 0;
            $smsFailed = 0;
            $noContact = 0;

            while ($userRow = mysqli_fetch_array($bulkRes, MYSQLI_ASSOC)) {
                $total++;
                $bulkContactMeta = adminPasswordResetResolveSmsTarget($userRow, 'Student');
                if ($bulkContactMeta['phone'] === '') {
                    $noContact++;
                    continue;
                }
                $newUsername = trim((string)(isset($userRow['username']) ? $userRow['username'] : ''));
                if ($newUsername === '') {
                    $newUsername = trim((string)$userRow['userid']);
                }
                $newPasswordRaw = adminPasswordResetGenerateTempPassword(8);
                $resetResult = adminPasswordResetApplyCredentialReset($con, $userRow, 'Student', $newUsername, $newPasswordRaw);

                if ($resetResult['ok']) {
                    $resetSuccess++;
                    if ($resetResult['sms_status'] === 'SENT') {
                        $smsSent++;
                    } elseif ($resetResult['sms_status'] === 'FAILED') {
                        $smsFailed++;
                    }
                } else {
                    $resetFailed++;
                }
            }

            if ($total === 0) {
                $_SESSION['Message'] = adminPasswordResetFlashHtml('warning', 'No active student records were found for the selected bulk scope.');
            } else {
                $scopeLabel = adminPasswordResetBuildScopeLabel($selectedBatchLabel, $selectedClassLabel);
                $_SESSION['Message'] = adminPasswordResetFlashHtml('success', "Bulk credential send completed for <strong>" . number_format($total) . "</strong> student(s) in <strong>" . apr_esc($scopeLabel) . "</strong>. Successful resets: <strong>" . number_format($resetSuccess) . "</strong>, SMS sent: <strong>" . number_format($smsSent) . "</strong>, SMS failed: <strong>" . number_format($smsFailed) . "</strong>, skipped for missing contact: <strong>" . number_format($noContact) . "</strong>, reset failures: <strong>" . number_format($resetFailed) . "</strong>.");
            }
        }
    }

    header("location:" . adminPasswordResetBuildRedirectUrl($resetType, $keyword, $selectedBatchId, $selectedClassId, $currentPage, $perPage));
    exit();
}

if (isset($_POST['admin_send_credentials_single'])) {
    $targetUserId = isset($_POST['target_userid']) ? trim((string)$_POST['target_userid']) : "";
    $resetType = isset($_POST['target_type']) ? trim((string)$_POST['target_type']) : "Student";
    $selectedBatchId = isset($_POST['scope_batchid']) ? trim((string)$_POST['scope_batchid']) : $selectedBatchId;
    $selectedClassId = isset($_POST['scope_classid']) ? trim((string)$_POST['scope_classid']) : $selectedClassId;
    $currentPage = isset($_POST['page']) ? (int)$_POST['page'] : $currentPage;
    if ($currentPage < 1) {
        $currentPage = 1;
    }
    $perPage = adminPasswordResetNormalizePerPage(isset($_POST['per_page']) ? $_POST['per_page'] : $perPage);

    if ($resetType !== "Teacher" && $resetType !== "Student") {
        $resetType = "Student";
    }

    if ($targetUserId === "") {
        $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'Select a valid user before sending credentials.');
    } else {
        $stmtCheck = mysqli_prepare($con, "SELECT userid,firstname,othernames,surname,username,mobile,nextofkin_fullname,nextofkin_contact,relationship
            FROM tblsystemuser
            WHERE userid=? AND systemtype=? LIMIT 1");
        if ($stmtCheck) {
            mysqli_stmt_bind_param($stmtCheck, "ss", $targetUserId, $resetType);
            mysqli_stmt_execute($stmtCheck);
            $resCheck = mysqli_stmt_get_result($stmtCheck);

            if ($resCheck && $userRow = mysqli_fetch_array($resCheck, MYSQLI_ASSOC)) {
                $smsTarget = adminPasswordResetResolveSmsTarget($userRow, $resetType);
                if ($smsTarget['phone'] === '') {
                    $label = ($resetType === 'Student') ? 'student or guardian' : 'teacher';
                    $_SESSION['Message'] = adminPasswordResetFlashHtml('warning', 'No ' . $label . ' contact is saved for <strong>' . apr_esc($targetUserId) . '</strong>, so no SMS was sent and the password was left unchanged.');
                } else {
                    $newUsername = trim((string)(isset($userRow['username']) ? $userRow['username'] : ''));
                    if ($newUsername === '') {
                        $newUsername = trim((string)$targetUserId);
                    }
                    $newPasswordRaw = adminPasswordResetGenerateTempPassword(8);
                    $resetResult = adminPasswordResetApplyCredentialReset($con, $userRow, $resetType, $newUsername, $newPasswordRaw);
                    if ($resetResult['ok']) {
                        $smsNoteClass = ($resetResult['sms_status'] === 'SENT') ? 'apr-flash__detail--success' : 'apr-flash__detail--warning';
                        $smsNote = $resetResult['sms_note'] !== '' ? "<div class='apr-flash__detail " . $smsNoteClass . "'>" . $resetResult['sms_note'] . "</div>" : "";
                        if ($resetResult['sms_status'] === 'SENT') {
                            $message = ($resetType === 'Student')
                                ? "Fresh student credentials were generated and sent for <strong>" . apr_esc($targetUserId) . "</strong>."
                                : "Teacher credentials were reset and the SMS notification was sent for <strong>" . apr_esc($targetUserId) . "</strong>.";
                        } else {
                            $message = ($resetType === 'Student')
                                ? "Fresh student credentials were generated for <strong>" . apr_esc($targetUserId) . "</strong>."
                                : "Teacher credentials were reset for <strong>" . apr_esc($targetUserId) . "</strong>.";
                        }
                        $_SESSION['Message'] = adminPasswordResetFlashHtml('success', $message . $smsNote);
                    } else {
                        $_SESSION['Message'] = adminPasswordResetFlashHtml('error', apr_esc($resetResult['message']));
                    }
                }
            } else {
                $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'Selected user was not found.');
            }
            mysqli_stmt_close($stmtCheck);
        } else {
            $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'Could not prepare the individual credential send query.');
        }
    }

    header("location:" . adminPasswordResetBuildRedirectUrl($resetType, $keyword, $selectedBatchId, $selectedClassId, $currentPage, $perPage));
    exit();
}

if (isset($_POST['admin_reset_password'])) {
    $targetUserId = isset($_POST['target_userid']) ? trim($_POST['target_userid']) : "";
    $newUsername = isset($_POST['new_username']) ? trim($_POST['new_username']) : "";
    $newPasswordRaw = isset($_POST['new_password']) ? trim($_POST['new_password']) : "";
    $resetType = isset($_POST['target_type']) ? trim($_POST['target_type']) : "Student";
    $selectedBatchId = isset($_POST['scope_batchid']) ? trim((string)$_POST['scope_batchid']) : $selectedBatchId;
    $selectedClassId = isset($_POST['scope_classid']) ? trim((string)$_POST['scope_classid']) : $selectedClassId;
    $currentPage = isset($_POST['page']) ? (int)$_POST['page'] : $currentPage;
    if ($currentPage < 1) {
        $currentPage = 1;
    }
    $perPage = adminPasswordResetNormalizePerPage(isset($_POST['per_page']) ? $_POST['per_page'] : $perPage);

    if ($resetType !== "Teacher" && $resetType !== "Student") {
        $resetType = "Student";
    }

    if ($targetUserId === "" || $newUsername === "" || $newPasswordRaw === "") {
        $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'User, username and password are required.');
    } elseif (strlen($newPasswordRaw) < 6) {
        $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'New password must be at least 6 characters.');
    } else {
        $stmtCheck = mysqli_prepare($con, "SELECT userid,firstname,othernames,surname,mobile,nextofkin_fullname,nextofkin_contact,relationship
            FROM tblsystemuser
            WHERE userid=? AND systemtype=? LIMIT 1");
        if ($stmtCheck) {
            mysqli_stmt_bind_param($stmtCheck, "ss", $targetUserId, $resetType);
            mysqli_stmt_execute($stmtCheck);
            $resCheck = mysqli_stmt_get_result($stmtCheck);

            if ($resCheck && $userRow = mysqli_fetch_array($resCheck, MYSQLI_ASSOC)) {
                $resetResult = adminPasswordResetApplyCredentialReset($con, $userRow, $resetType, $newUsername, $newPasswordRaw);
                if ($resetResult['ok']) {
                    $smsNote = $resetResult['sms_note'] !== '' ? "<div class='apr-flash__detail apr-flash__detail--success'>" . $resetResult['sms_note'] . "</div>" : "";
                    if ($resetResult['sms_status'] === 'FAILED' || $resetResult['sms_status'] === 'NO_PHONE' || $resetResult['sms_status'] === 'NO_CONTACT') {
                        $smsNote = $resetResult['sms_note'] !== '' ? "<div class='apr-flash__detail apr-flash__detail--warning'>" . $resetResult['sms_note'] . "</div>" : "";
                    }
                    $_SESSION['Message'] = adminPasswordResetFlashHtml('success', "Password reset successful for <strong>" . apr_esc($targetUserId) . "</strong>." . $smsNote);
                } else {
                    $_SESSION['Message'] = adminPasswordResetFlashHtml('error', apr_esc($resetResult['message']));
                }
            } else {
                $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'Selected user was not found.');
            }
            mysqli_stmt_close($stmtCheck);
        } else {
            $_SESSION['Message'] = adminPasswordResetFlashHtml('error', 'Reset failed. Could not prepare lookup query.');
        }
    }

    header("location:" . adminPasswordResetBuildRedirectUrl($resetType, $keyword, $selectedBatchId, $selectedClassId, $currentPage, $perPage));
    exit();
}

$listRows = array();
$rowsFound = 0;
$studentScopeActive = ($targetType === 'Student' && ($selectedBatchId !== '' || $selectedClassId !== ''));
$scopeLabel = adminPasswordResetBuildScopeLabel($selectedBatchLabel, $selectedClassLabel);
$totalMatchingRows = 0;
$totalPages = 1;
$offset = 0;
$rangeStart = 0;
$rangeEnd = 0;
$scopeStudentCount = 0;
$scopeReachableCount = 0;

if ($targetType === 'Student') {
    $keywordSafe = mysqli_real_escape_string($con, $keyword);
    $batchSafe = mysqli_real_escape_string($con, $selectedBatchId);
    $classSafe = mysqli_real_escape_string($con, $selectedClassId);

    $studentWhere = "su.systemtype='Student'";

    if ($keyword !== '') {
        $studentWhere .= " AND (
            su.userid LIKE '%" . $keywordSafe . "%'
            OR su.firstname LIKE '%" . $keywordSafe . "%'
            OR su.othernames LIKE '%" . $keywordSafe . "%'
            OR su.surname LIKE '%" . $keywordSafe . "%'
        )";
    }

    if ($studentScopeActive) {
        $studentWhere .= " AND EXISTS (
            SELECT 1
            FROM tblclass cl
            WHERE cl.userid=su.userid
              AND cl.status='active'";
        if ($selectedBatchId !== '') {
            $studentWhere .= " AND cl.batchid='" . $batchSafe . "'";
        }
        if ($selectedClassId !== '') {
            $studentWhere .= " AND cl.class_entryid='" . $classSafe . "'";
        }
        $studentWhere .= ")";
    }

    $countSql = "SELECT COUNT(*) AS total
        FROM tblsystemuser su
        WHERE " . $studentWhere;
    $countRes = mysqli_query($con, $countSql);
    if ($countRes && $countRow = mysqli_fetch_array($countRes, MYSQLI_ASSOC)) {
        $totalMatchingRows = (int)$countRow['total'];
    }

    $totalPages = max(1, (int)ceil($totalMatchingRows / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $perPage;

    $sql = "SELECT
            su.userid,
            su.firstname,
            su.othernames,
            su.surname,
            su.username,
            su.systemtype,
            su.mobile,
            su.nextofkin_contact,
            (
                SELECT ce.class_name
                FROM tblclass cl2
                INNER JOIN tblclassentry ce ON ce.class_entryid=cl2.class_entryid
                WHERE cl2.userid=su.userid
                  AND cl2.status='active'
                ORDER BY cl2.datetimeentry DESC, ce.class_name ASC
                LIMIT 1
            ) AS current_class_name,
            (
                SELECT bh.batch
                FROM tblclass cl3
                INNER JOIN tblbatch bh ON bh.batchid=cl3.batchid
                WHERE cl3.userid=su.userid
                  AND cl3.status='active'
                ORDER BY cl3.datetimeentry DESC, bh.datetimeentry DESC
                LIMIT 1
            ) AS current_batch_name
        FROM tblsystemuser su
        WHERE " . $studentWhere . "
        ORDER BY su.firstname ASC,su.surname ASC,su.othernames ASC
        LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $resList = mysqli_query($con, $sql);
    if ($resList) {
        while ($row = mysqli_fetch_array($resList, MYSQLI_ASSOC)) {
            $listRows[] = $row;
        }
    }

    if ($studentScopeActive) {
        $scopeCountSql = "SELECT
                COUNT(*) AS total,
                SUM(
                    CASE
                        WHEN TRIM(REPLACE(IFNULL(su.mobile, ''), ' ', '')) <> ''
                          OR TRIM(REPLACE(IFNULL(su.nextofkin_contact, ''), ' ', '')) <> ''
                        THEN 1
                        ELSE 0
                    END
                ) AS reachable_total
            FROM tblsystemuser su
            WHERE su.systemtype='Student'
              AND su.status='active'
              AND EXISTS (
                SELECT 1
                FROM tblclass cl
                WHERE cl.userid=su.userid
                  AND cl.status='active'";
        if ($selectedBatchId !== '') {
            $scopeCountSql .= " AND cl.batchid='" . $batchSafe . "'";
        }
        if ($selectedClassId !== '') {
            $scopeCountSql .= " AND cl.class_entryid='" . $classSafe . "'";
        }
        $scopeCountSql .= ")";

        $scopeCountRes = mysqli_query($con, $scopeCountSql);
        if ($scopeCountRes && $scopeCountRow = mysqli_fetch_array($scopeCountRes, MYSQLI_ASSOC)) {
            $scopeStudentCount = (int)$scopeCountRow['total'];
            $scopeReachableCount = (int)$scopeCountRow['reachable_total'];
        }
    }
} else {
    $teacherWhere = "systemtype=?";
    $teacherLike = "%" . $keyword . "%";

    if ($keyword !== "") {
        $teacherWhere .= " AND (userid LIKE ? OR firstname LIKE ? OR othernames LIKE ? OR surname LIKE ?)";
    }

    $countSql = "SELECT COUNT(*) AS total FROM tblsystemuser WHERE " . $teacherWhere;
    $stmtCount = mysqli_prepare($con, $countSql);
    if ($stmtCount) {
        if ($keyword !== "") {
            mysqli_stmt_bind_param($stmtCount, "sssss", $targetType, $teacherLike, $teacherLike, $teacherLike, $teacherLike);
        } else {
            mysqli_stmt_bind_param($stmtCount, "s", $targetType);
        }
        mysqli_stmt_execute($stmtCount);
        $countResult = mysqli_stmt_get_result($stmtCount);
        if ($countResult && $countRow = mysqli_fetch_array($countResult, MYSQLI_ASSOC)) {
            $totalMatchingRows = (int)$countRow['total'];
        }
        mysqli_stmt_close($stmtCount);
    } else {
    }

    $totalPages = max(1, (int)ceil($totalMatchingRows / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $perPage;

    $stmtList = false;
    $sql = "SELECT userid,firstname,othernames,surname,username,systemtype,mobile,nextofkin_contact
            FROM tblsystemuser
            WHERE " . $teacherWhere . "
            ORDER BY firstname ASC,surname ASC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $stmtList = mysqli_prepare($con, $sql);
    if ($stmtList) {
        if ($keyword !== "") {
            mysqli_stmt_bind_param($stmtList, "sssss", $targetType, $teacherLike, $teacherLike, $teacherLike, $teacherLike);
        } else {
            mysqli_stmt_bind_param($stmtList, "s", $targetType);
        }
    }

    if ($stmtList) {
        mysqli_stmt_execute($stmtList);
        $resList = mysqli_stmt_get_result($stmtList);
        if ($resList) {
            while ($row = mysqli_fetch_array($resList, MYSQLI_ASSOC)) {
                $listRows[] = $row;
            }
        }
        mysqli_stmt_close($stmtList);
    }
}

$rowsFound = count($listRows);
$rangeStart = ($totalMatchingRows > 0) ? ($offset + 1) : 0;
$rangeEnd = ($rowsFound > 0) ? ($offset + $rowsFound) : 0;
$reachableContactCount = 0;
$missingContactCount = 0;
$visibleStudentCount = 0;
$visibleTeacherCount = 0;

foreach ($listRows as $row) {
    if ((string)(isset($row['systemtype']) ? $row['systemtype'] : '') === 'Teacher') {
        $visibleTeacherCount++;
    } else {
        $visibleStudentCount++;
    }

    $contactMeta = adminPasswordResetResolveSmsTarget($row, isset($row['systemtype']) ? $row['systemtype'] : '');
    if ($contactMeta['phone'] !== '') {
        $reachableContactCount++;
    } else {
        $missingContactCount++;
    }
}

$heroKicker = ($targetType === 'Student') ? 'Student Credential Delivery' : 'Teacher Credential Reset';
$heroTitle = ($targetType === 'Student') ? 'Send Student Login Credentials' : 'Reset Teacher Login Credentials';
$heroIntro = ($targetType === 'Student')
    ? 'Select a batch or class, confirm the contact on file, and send fresh student portal credentials by SMS. Students will still be required to change the password after login.'
    : 'Search for a teacher, confirm the registered phone number, and send a new temporary password by SMS.';
$heroScopeCopy = ($targetType === 'Student')
    ? ($studentScopeActive ? $scopeLabel : 'Select a batch or class to unlock bulk sending.')
    : 'Teacher direct SMS resets only';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/admin-password-reset.css">
</head>
<body class="admin-password-reset-page">
<div class="header">
<?php include("menu.php"); ?>
</div>

<main class="apr-shell">
    <section class="apr-hero">
        <div class="apr-hero__copy">
            <span class="apr-kicker"><i class="fa fa-shield"></i> <?php echo apr_esc($heroKicker); ?></span>
            <h1><?php echo apr_esc($heroTitle); ?></h1>
            <p><?php echo apr_esc($heroIntro); ?></p>
            <div class="apr-hero__chips">
                <span class="apr-chip"><i class="fa fa-filter"></i> Scope: <?php echo apr_esc($heroScopeCopy); ?></span>
                <span class="apr-chip"><i class="fa fa-comments"></i> SMS to registered contact</span>
                <span class="apr-chip"><i class="fa fa-lock"></i> Password change required</span>
            </div>
        </div>
        <div class="apr-stats">
            <article class="apr-stat">
                <span>Matching Records</span>
                <strong><?php echo number_format((int)$totalMatchingRows); ?></strong>
                <small><?php echo ($rowsFound > 0) ? 'Showing ' . number_format((int)$rangeStart) . ' to ' . number_format((int)$rangeEnd) . ' on page ' . number_format((int)$currentPage) . ' of ' . number_format((int)$totalPages) . '.' : 'No records match the current filters.'; ?></small>
            </article>
            <article class="apr-stat">
                <span>Reachable On Page</span>
                <strong><?php echo number_format((int)$reachableContactCount); ?></strong>
                <small>Visible records with a contact number ready for credential delivery.</small>
            </article>
            <article class="apr-stat">
                <span>Missing Contacts</span>
                <strong><?php echo number_format((int)$missingContactCount); ?></strong>
                <small>Visible records that still need a trusted phone number.</small>
            </article>
            <article class="apr-stat apr-stat--accent">
                <span><?php echo ($targetType === 'Student') ? 'Bulk Scope' : 'Reset Mode'; ?></span>
                <strong><?php echo ($targetType === 'Student' && $studentScopeActive) ? number_format((int)$scopeStudentCount) : (($targetType === 'Teacher') ? number_format((int)$visibleTeacherCount) : 'Ready'); ?></strong>
                <small><?php echo ($targetType === 'Student') ? ($studentScopeActive ? number_format((int)$scopeReachableCount) . ' contacts are reachable in the selected class or batch scope.' : 'Choose a class or batch to unlock bulk sending.') : 'Teacher resets stay single-record for safety.'; ?></small>
            </article>
        </div>
    </section>

    <div class="apr-layout">
        <aside class="apr-sidebar apr-surface">
            <?php include("menuboard.php"); ?>
        </aside>
        <section class="apr-main">
            <?php
            if (isset($_SESSION['Message']) && $_SESSION['Message'] !== "") {
                echo $_SESSION['Message'];
                $_SESSION['Message'] = "";
            }
            ?>

            <section class="apr-surface">
                <div class="apr-panel-head">
                    <div>
                        <span class="apr-panel-kicker">Search And Filter</span>
                        <h2>Find the users you want to work on</h2>
                        <p>Switch between students and teachers, narrow the student list by batch or class, and review the available contacts before sending credentials.</p>
                    </div>
                    <span class="apr-panel-tag"><i class="fa fa-database"></i> Current records</span>
                </div>
                <form method="get" action="admin-password-reset.php" class="apr-filter-form">
                    <div class="apr-filter-grid<?php echo ($targetType === 'Student') ? '' : ' apr-filter-grid--compact'; ?>">
                        <label class="apr-field">
                            <span>User Type</span>
                            <select id="type" name="type">
                                <option value="Student" <?php echo ($targetType === "Student" ? "selected" : ""); ?>>Student</option>
                                <option value="Teacher" <?php echo ($targetType === "Teacher" ? "selected" : ""); ?>>Teacher</option>
                            </select>
                        </label>
                        <label class="apr-field apr-field--search">
                            <span>Search</span>
                            <input type="text" id="q" name="q" value="<?php echo apr_esc($keyword); ?>" placeholder="Search by user ID, surname, or name">
                        </label>
                        <?php if ($targetType === "Student") { ?>
                        <label class="apr-field">
                            <span>Batch</span>
                            <select id="scope_batchid" name="scope_batchid">
                                <option value="">All Batches</option>
                                <?php foreach ($batchOptions as $batchOption) { ?>
                                <option value="<?php echo apr_esc($batchOption['batchid']); ?>"<?php echo ($selectedBatchId === trim((string)$batchOption['batchid']) ? " selected" : ""); ?>><?php echo apr_esc($batchOption['batch']); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label class="apr-field">
                            <span>Class</span>
                            <select id="scope_classid" name="scope_classid">
                                <option value="">All Classes</option>
                                <?php foreach ($classOptions as $classOption) { ?>
                                <option value="<?php echo apr_esc($classOption['class_entryid']); ?>"<?php echo ($selectedClassId === trim((string)$classOption['class_entryid']) ? " selected" : ""); ?>><?php echo apr_esc($classOption['class_name']); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <?php } ?>
                        <label class="apr-field">
                            <span>Rows Per Page</span>
                            <select id="per_page" name="per_page">
                                <option value="50"<?php echo ($perPage === 50 ? " selected" : ""); ?>>50 rows</option>
                                <option value="100"<?php echo ($perPage === 100 ? " selected" : ""); ?>>100 rows</option>
                                <option value="250"<?php echo ($perPage === 250 ? " selected" : ""); ?>>250 rows</option>
                                <option value="500"<?php echo ($perPage === 500 ? " selected" : ""); ?>>500 rows</option>
                            </select>
                        </label>
                    </div>
                    <div class="apr-actions">
                        <button class="apr-btn apr-btn--primary" type="submit"><i class="fa fa-search"></i> Apply Search</button>
                        <a class="apr-btn apr-btn--secondary" href="admin-password-reset.php?type=<?php echo urlencode($targetType); ?>&per_page=<?php echo (int)$perPage; ?>"><i class="fa fa-undo"></i> Reset Filters</a>
                    </div>
                </form>
            </section>

            <?php if ($targetType === "Student") { ?>
            <section class="apr-surface apr-bulk-panel">
                <div class="apr-panel-head">
                    <div>
                        <span class="apr-panel-kicker">Bulk Delivery</span>
                        <h2>Send Student Credentials In Bulk</h2>
                        <p>Bulk sending keeps each student's current username, generates a temporary password, and sends it to the registered contact on the student record.</p>
                    </div>
                    <span class="apr-panel-tag"><i class="fa fa-envelope"></i> Batch or class scope</span>
                </div>
                <div class="apr-bulk-meta">
                    <span class="apr-chip apr-chip--light"><i class="fa fa-filter"></i> Scope: <?php echo apr_esc($scopeLabel); ?></span>
                    <span class="apr-chip apr-chip--light"><i class="fa fa-users"></i> Students In Scope: <?php echo number_format((int)$scopeStudentCount); ?></span>
                    <span class="apr-chip apr-chip--light"><i class="fa fa-phone"></i> Reachable Contacts: <?php echo number_format((int)$scopeReachableCount); ?></span>
                </div>
                <form method="post" action="<?php echo apr_esc(adminPasswordResetBuildRedirectUrl($targetType, $keyword, $selectedBatchId, $selectedClassId, $currentPage, $perPage)); ?>" class="apr-bulk-form">
                    <div class="apr-bulk-form__copy">
                        Run this only after confirming the selected scope and contact coverage. The bulk action uses the chosen batch and class filters, not the search box. Students without a valid phone number are skipped.
                    </div>
                    <div class="apr-bulk-form__actions">
                        <input type="hidden" name="target_type" value="Student">
                        <input type="hidden" name="q" value="<?php echo apr_esc($keyword); ?>">
                        <input type="hidden" name="scope_batchid" value="<?php echo apr_esc($selectedBatchId); ?>">
                        <input type="hidden" name="scope_classid" value="<?php echo apr_esc($selectedClassId); ?>">
                        <input type="hidden" name="page" value="<?php echo (int)$currentPage; ?>">
                        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
                        <button class="apr-btn apr-btn--primary" type="submit" name="admin_bulk_reset_password" <?php echo ($studentScopeActive && $scopeStudentCount > 0) ? "" : "disabled"; ?> onclick="return confirm('Reset and send credentials in bulk for <?php echo (int)$scopeStudentCount; ?> student(s) under <?php echo apr_esc($scopeLabel); ?>? This will generate new temporary passwords for every matching student in the selected scope.');"><i class="fa fa-paper-plane"></i> Reset And Send In Bulk</button>
                    </div>
                </form>
                <?php if (!$studentScopeActive) { ?>
                <div class="apr-inline-note apr-inline-note--warning"><i class="fa fa-info-circle"></i> Choose a batch, class, or both above before running the bulk action.</div>
                <?php } elseif ($scopeStudentCount === 0) { ?>
                <div class="apr-inline-note apr-inline-note--warning"><i class="fa fa-exclamation-triangle"></i> The selected scope currently has no matching student records to send.</div>
                <?php } else { ?>
                <div class="apr-inline-note"><i class="fa fa-check-circle"></i> Scope is ready. The table below can still be narrowed with search, but the bulk button always uses the full selected class and batch scope.</div>
                <?php } ?>
            </section>
            <?php } ?>

            <section class="apr-surface" id="apr-results">
                <div class="apr-panel-head">
                    <div>
                        <span class="apr-panel-kicker">Delivery List</span>
                        <h2>Review contacts and send individual resets</h2>
                        <p>The quick action generates a temporary password and sends it immediately by SMS. Use the advanced options only when you need a custom username or password.</p>
                    </div>
                    <span class="apr-panel-tag"><i class="fa fa-list"></i> <?php echo ($totalMatchingRows > 0) ? number_format((int)$rangeStart) . '-' . number_format((int)$rangeEnd) : '0'; ?> of <?php echo number_format((int)$totalMatchingRows); ?></span>
                </div>
                <div class="apr-table-wrap">
                    <table class="apr-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <?php if ($targetType === "Student") { ?>
                                <th>Current Batch</th>
                                <th>Current Class</th>
                                <?php } ?>
                                <th>SMS Contact</th>
                                <th>Current Username</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach ($listRows as $row) {
                            $fullName = trim($row['firstname'] . " " . $row['othernames'] . " " . $row['surname']);
                            $smsTarget = adminPasswordResetResolveSmsTarget($row, $row['systemtype']);
                            $defaultUsername = trim((string)(isset($row['username']) ? $row['username'] : ''));
                            if ($defaultUsername === '') {
                                $defaultUsername = trim((string)$row['userid']);
                            }
                            $quickActionLabel = ($row['systemtype'] === 'Student') ? 'Reset And Send SMS' : 'Reset And Notify';
                            echo "<tr>";
                            echo "<td data-label='User ID'>" . apr_esc($row['userid']) . "</td>";
                            echo "<td data-label='Name'><strong>" . apr_esc($fullName !== '' ? $fullName : $row['userid']) . "</strong></td>";
                            if ($row['systemtype'] === "Teacher") {
                                echo "<td data-label='Type'><span class='apr-badge apr-badge--teacher'>Teacher</span></td>";
                            } else {
                                echo "<td data-label='Type'><span class='apr-badge apr-badge--student'>Student</span></td>";
                            }
                            if ($targetType === "Student") {
                                echo "<td data-label='Current Batch'>" . apr_esc((string)(isset($row['current_batch_name']) ? $row['current_batch_name'] : '')) . "</td>";
                                echo "<td data-label='Current Class'>" . apr_esc((string)(isset($row['current_class_name']) ? $row['current_class_name'] : '')) . "</td>";
                            }
                            echo "<td data-label='SMS Contact'>";
                            if ($smsTarget['phone'] !== '') {
                                echo "<div class='apr-contact'><strong>" . apr_esc($smsTarget['phone']) . "</strong><small>" . apr_esc($smsTarget['label']) . "</small></div>";
                            } else {
                                echo "<span class='apr-contact apr-contact--missing'>No contact</span>";
                            }
                            echo "</td>";
                            echo "<td data-label='Current Username'><span class='apr-username'>" . apr_esc($defaultUsername) . "</span></td>";
                            echo "<td data-label='Actions'>";
                            echo "<div class='apr-row-actions'>";
                            echo "<form method='post' action='" . apr_esc(adminPasswordResetBuildRedirectUrl($targetType, $keyword, $selectedBatchId, $selectedClassId, $currentPage, $perPage)) . "' class='apr-quick-form'>";
                            echo "<input type='hidden' name='target_userid' value='" . apr_esc($row['userid']) . "'>";
                            echo "<input type='hidden' name='target_type' value='" . apr_esc($row['systemtype']) . "'>";
                            echo "<input type='hidden' name='q' value='" . apr_esc($keyword) . "'>";
                            echo "<input type='hidden' name='scope_batchid' value='" . apr_esc($selectedBatchId) . "'>";
                            echo "<input type='hidden' name='scope_classid' value='" . apr_esc($selectedClassId) . "'>";
                            echo "<input type='hidden' name='page' value='" . (int)$currentPage . "'>";
                            echo "<input type='hidden' name='per_page' value='" . (int)$perPage . "'>";
                            echo "<button class='apr-btn apr-btn--primary apr-btn--inline' type='submit' name='admin_send_credentials_single' " . ($smsTarget['phone'] === '' ? "disabled" : "") . " onclick=\"return confirm('Generate a fresh temporary password and send it by SMS for this user?');\"><i class='fa fa-paper-plane'></i> " . apr_esc($quickActionLabel) . "</button>";
                            echo "</form>";
                            if ($smsTarget['phone'] === '') {
                                echo "<div class='apr-action-note apr-action-note--warning'><i class='fa fa-info-circle'></i> Add a phone number before sending by SMS.</div>";
                            } else {
                                echo "<div class='apr-action-note'><i class='fa fa-lock'></i> A temporary password is generated automatically and the user must change it on next login.</div>";
                            }
                            echo "<details class='apr-manual-reset'>";
                            echo "<summary>Advanced reset options</summary>";
                            echo "<form method='post' action='" . apr_esc(adminPasswordResetBuildRedirectUrl($targetType, $keyword, $selectedBatchId, $selectedClassId, $currentPage, $perPage)) . "' class='apr-inline-form'>";
                            echo "<input type='hidden' name='target_userid' value='" . apr_esc($row['userid']) . "'>";
                            echo "<input type='hidden' name='target_type' value='" . apr_esc($row['systemtype']) . "'>";
                            echo "<input type='hidden' name='q' value='" . apr_esc($keyword) . "'>";
                            echo "<input type='hidden' name='scope_batchid' value='" . apr_esc($selectedBatchId) . "'>";
                            echo "<input type='hidden' name='scope_classid' value='" . apr_esc($selectedClassId) . "'>";
                            echo "<input type='hidden' name='page' value='" . (int)$currentPage . "'>";
                            echo "<input type='hidden' name='per_page' value='" . (int)$perPage . "'>";
                            echo "<input type='text' name='new_username' value='" . apr_esc($defaultUsername) . "' placeholder='New username' required>";
                            echo "<input type='password' name='new_password' placeholder='New password (min 6 chars)' required minlength='6'>";
                            echo "<button class='apr-btn apr-btn--secondary apr-btn--inline' type='submit' name='admin_reset_password' onclick=\"return confirm('Apply a custom credential reset for this user?');\"><i class='fa fa-key'></i> Apply Custom Reset</button>";
                            echo "</form>";
                            echo "</details>";
                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                        }

                        if ($rowsFound === 0) {
                            $emptyTitle = "No " . strtolower($targetType) . " records found";
                            $emptyMessage = ($targetType === 'Student')
                                ? "Try widening the batch or class filter, or search with a different student name or ID."
                                : "Try a different teacher name, ID, or clear the search to see more results.";
                            echo "<tr><td colspan='" . (int)(($targetType === 'Student') ? 8 : 6) . "'><div class='apr-empty-state'><h3>" . apr_esc($emptyTitle) . "</h3><p>" . apr_esc($emptyMessage) . "</p></div></td></tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1) { ?>
                <nav class="apr-pagination" aria-label="Credential list pages">
                    <div class="apr-pagination__summary">
                        Showing <?php echo number_format((int)$rangeStart); ?> to <?php echo number_format((int)$rangeEnd); ?> of <?php echo number_format((int)$totalMatchingRows); ?> record<?php echo ($totalMatchingRows === 1 ? '' : 's'); ?>.
                    </div>
                    <div class="apr-pagination__links">
                        <?php if ($currentPage > 1) { ?>
                        <a class="apr-page-link" href="<?php echo apr_esc(adminPasswordResetBuildRedirectUrl($targetType, $keyword, $selectedBatchId, $selectedClassId, $currentPage - 1, $perPage)); ?>"><i class="fa fa-angle-left"></i> Previous</a>
                        <?php } else { ?>
                        <span class="apr-page-link apr-page-link--disabled"><i class="fa fa-angle-left"></i> Previous</span>
                        <?php } ?>

                        <?php
                        $pageWindowStart = max(1, $currentPage - 2);
                        $pageWindowEnd = min($totalPages, $currentPage + 2);
                        for ($pageNumber = $pageWindowStart; $pageNumber <= $pageWindowEnd; $pageNumber++) {
                            if ($pageNumber === $currentPage) {
                                echo "<span class='apr-page-link apr-page-link--active'>" . number_format((int)$pageNumber) . "</span>";
                            } else {
                                echo "<a class='apr-page-link' href='" . apr_esc(adminPasswordResetBuildRedirectUrl($targetType, $keyword, $selectedBatchId, $selectedClassId, $pageNumber, $perPage)) . "'>" . number_format((int)$pageNumber) . "</a>";
                            }
                        }
                        ?>

                        <?php if ($currentPage < $totalPages) { ?>
                        <a class="apr-page-link" href="<?php echo apr_esc(adminPasswordResetBuildRedirectUrl($targetType, $keyword, $selectedBatchId, $selectedClassId, $currentPage + 1, $perPage)); ?>">Next <i class="fa fa-angle-right"></i></a>
                        <?php } else { ?>
                        <span class="apr-page-link apr-page-link--disabled">Next <i class="fa fa-angle-right"></i></span>
                        <?php } ?>
                    </div>
                </nav>
                <?php } ?>
                <p class="apr-footnote">Only administrator accounts can access this page. Student resets send the username and temporary password by SMS to the registered contact shown above, and bulk sending uses the selected class and batch scope.</p>
            </section>
        </section>
    </div>
</main>
</body>
</html>
