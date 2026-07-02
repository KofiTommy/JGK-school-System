<?php
if (!function_exists('ensureSystemChangeLogTable')) {
    function ensureSystemChangeLogTable($con)
    {
        static $done = false;
        if ($done || !$con) {
            return;
        }
        $done = true;
        @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblsystemchangelog (
            logid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_userid VARCHAR(60) NOT NULL,
            actor_name VARCHAR(180) DEFAULT '',
            actor_type VARCHAR(40) DEFAULT '',
            action_type VARCHAR(80) NOT NULL,
            target_userid VARCHAR(60) DEFAULT '',
            details TEXT,
            page_name VARCHAR(120) DEFAULT '',
            ip_address VARCHAR(64) DEFAULT '',
            datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'unread',
            PRIMARY KEY (logid),
            KEY idx_datetimeentry (datetimeentry),
            KEY idx_status (status),
            KEY idx_action_type (action_type),
            KEY idx_actor_type (actor_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('logSystemChange')) {
    function logSystemChange($con, $actionType, $details = '', $targetUserId = '')
    {
        if (!$con || trim((string)$actionType) === '') {
            return false;
        }

        ensureSystemChangeLogTable($con);

        $actorUserId = isset($_SESSION['USERID']) ? $_SESSION['USERID'] : '';
        $actorName = isset($_SESSION['FULLNAME']) ? $_SESSION['FULLNAME'] : '';
        $actorType = isset($_SESSION['SYSTEMTYPE']) ? $_SESSION['SYSTEMTYPE'] : '';
        $pageName = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : '';
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        $sql = "INSERT INTO tblsystemchangelog
        (actor_userid, actor_name, actor_type, action_type, target_userid, details, page_name, ip_address, datetimeentry, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'unread')";

        $stmt = @mysqli_prepare($con, $sql);
        if (!$stmt) {
            return false;
        }

        @mysqli_stmt_bind_param(
            $stmt,
            "ssssssss",
            $actorUserId,
            $actorName,
            $actorType,
            $actionType,
            $targetUserId,
            $details,
            $pageName,
            $ipAddress
        );

        $ok = @mysqli_stmt_execute($stmt);
        @mysqli_stmt_close($stmt);
        return $ok ? true : false;
    }
}
?>
