<?php
session_start();

include("dbstring.php");
include("check-login.php");

if (!function_exists("sms_alert_esc")) {
    function sms_alert_esc($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("sms_alert_flash_html")) {
    function sms_alert_flash_html($tone, $message)
    {
        $tone = trim((string)$tone);
        $allowed = array("success", "error", "warning", "info");
        if (!in_array($tone, $allowed, true)) {
            $tone = "info";
        }

        $icon = "fa-info-circle";
        if ($tone === "success") {
            $icon = "fa-check-circle";
        } elseif ($tone === "error") {
            $icon = "fa-exclamation-circle";
        } elseif ($tone === "warning") {
            $icon = "fa-exclamation-triangle";
        }

        return "<div class='sms-alert-flash sms-alert-flash--" . $tone . "'><span class='sms-alert-flash__icon'><i class='fa " . $icon . "'></i></span><div class='sms-alert-flash__body'>" . $message . "</div></div>";
    }
}

if (!function_exists("sms_alert_student_name")) {
    function sms_alert_student_name($row)
    {
        $parts = array(
            trim((string)(isset($row["firstname"]) ? $row["firstname"] : "")),
            trim((string)(isset($row["othernames"]) ? $row["othernames"] : "")),
            trim((string)(isset($row["surname"]) ? $row["surname"] : ""))
        );
        $parts = array_filter($parts, static function ($part) {
            return $part !== "";
        });
        $name = trim(implode(" ", $parts));
        if ($name !== "") {
            return $name;
        }
        return trim((string)(isset($row["userid"]) ? $row["userid"] : "Unknown Student"));
    }
}

if (!function_exists("sms_alert_class_options")) {
    function sms_alert_class_options($con)
    {
        $rows = array();
        $sql = mysqli_query($con, "SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
        if ($sql) {
            while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists("sms_alert_scope_user_ids")) {
    function sms_alert_scope_user_ids($con, $classEntryId)
    {
        $classEntryId = trim((string)$classEntryId);
        if ($classEntryId === "") {
            return array();
        }

        $classEntryIdSafe = mysqli_real_escape_string($con, $classEntryId);
        $userIds = array();
        $sql = mysqli_query($con, "SELECT DISTINCT su.userid
            FROM tblsystemuser su
            INNER JOIN tblclass cl ON su.userid=cl.userid
            WHERE cl.class_entryid='$classEntryIdSafe'
              AND cl.status='active'
              AND su.systemtype='Student'
            ORDER BY su.userid ASC");
        if ($sql) {
            while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
                $userId = trim((string)$row["userid"]);
                if ($userId !== "") {
                    $userIds[] = $userId;
                }
            }
        }
        return $userIds;
    }
}

if (!function_exists("sms_alert_student_rows")) {
    function sms_alert_student_rows($con, $classEntryId)
    {
        $classEntryId = trim((string)$classEntryId);
        if ($classEntryId === "") {
            return array();
        }

        $classEntryIdSafe = mysqli_real_escape_string($con, $classEntryId);
        $rows = array();
        $sql = mysqli_query($con, "SELECT DISTINCT
                su.userid,
                su.firstname,
                su.surname,
                su.othernames,
                su.mobile,
                su.nextofkin_contact,
                su.username,
                COALESCE(su.smsalert, 0) AS smsalert
            FROM tblsystemuser su
            INNER JOIN tblclass cl ON su.userid=cl.userid
            WHERE cl.class_entryid='$classEntryIdSafe'
              AND cl.status='active'
              AND su.systemtype='Student'
            ORDER BY su.firstname ASC, su.surname ASC, su.othernames ASC, su.userid ASC");
        if ($sql) {
            while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

$selectedClassId = "";
if (isset($_GET["class_entryid"])) {
    $selectedClassId = trim((string)$_GET["class_entryid"]);
} elseif (isset($_POST["class_entryid"])) {
    $selectedClassId = trim((string)$_POST["class_entryid"]);
}

if (isset($_POST["save_sms_alerts"])) {
    $selectedClassId = isset($_POST["class_entryid"]) ? trim((string)$_POST["class_entryid"]) : "";
    if ($selectedClassId === "") {
        $_SESSION["Message"] = sms_alert_flash_html("error", "Select a class before saving SMS alert settings.");
        header("location:enablesmsalert.php");
        exit();
    }

    $scopeUserIds = sms_alert_scope_user_ids($con, $selectedClassId);
    if (empty($scopeUserIds)) {
        $_SESSION["Message"] = sms_alert_flash_html("warning", "No active students were found in the selected class.");
        header("location:enablesmsalert.php?class_entryid=" . urlencode($selectedClassId));
        exit();
    }

    $allowedMap = array_fill_keys($scopeUserIds, true);
    $selectedUserIds = isset($_POST["smscheck"]) && is_array($_POST["smscheck"]) ? $_POST["smscheck"] : array();
    $selectedScopedIds = array();
    foreach ($selectedUserIds as $rawUserId) {
        $userId = trim((string)$rawUserId);
        if ($userId !== "" && isset($allowedMap[$userId])) {
            $selectedScopedIds[$userId] = $userId;
        }
    }
    $selectedScopedIds = array_values($selectedScopedIds);

    $scopeSafeIds = array();
    foreach ($scopeUserIds as $userId) {
        $scopeSafeIds[] = "'" . mysqli_real_escape_string($con, $userId) . "'";
    }

    $savedOk = false;
    if (!empty($scopeSafeIds)) {
        $scopeIdList = implode(",", $scopeSafeIds);
        $resetOk = mysqli_query($con, "UPDATE tblsystemuser SET smsalert=0 WHERE userid IN ($scopeIdList)");
        $enableOk = true;

        if ($resetOk && !empty($selectedScopedIds)) {
            $enableSafeIds = array();
            foreach ($selectedScopedIds as $userId) {
                $enableSafeIds[] = "'" . mysqli_real_escape_string($con, $userId) . "'";
            }
            $enableIdList = implode(",", $enableSafeIds);
            $enableOk = mysqli_query($con, "UPDATE tblsystemuser SET smsalert=1 WHERE userid IN ($enableIdList)");
        }

        $savedOk = ($resetOk && $enableOk);
    }

    if ($savedOk) {
        $enabledCount = count($selectedScopedIds);
        $disabledCount = max(count($scopeUserIds) - $enabledCount, 0);
        $_SESSION["Message"] = sms_alert_flash_html(
            "success",
            "SMS alert settings saved. Enabled: <strong>" . number_format((int)$enabledCount) . "</strong>. Disabled: <strong>" . number_format((int)$disabledCount) . "</strong>."
        );
    } else {
        $_SESSION["Message"] = sms_alert_flash_html("error", "SMS alert settings could not be saved. Please try again.");
    }

    header("location:enablesmsalert.php?class_entryid=" . urlencode($selectedClassId));
    exit();
}

$classOptions = sms_alert_class_options($con);
$selectedClassLabel = "";
foreach ($classOptions as $classOption) {
    if ($selectedClassId !== "" && $selectedClassId === trim((string)$classOption["class_entryid"])) {
        $selectedClassLabel = trim((string)$classOption["class_name"]);
        break;
    }
}

$studentRows = array();
if ($selectedClassId !== "") {
    $studentRows = sms_alert_student_rows($con, $selectedClassId);
}

$totalStudents = count($studentRows);
$enabledCount = 0;
$disabledCount = 0;
$reachableCount = 0;
$missingContactCount = 0;

foreach ($studentRows as $row) {
    $smsEnabled = (int)(isset($row["smsalert"]) ? $row["smsalert"] : 0) === 1;
    if ($smsEnabled) {
        $enabledCount++;
    } else {
        $disabledCount++;
    }

    $mobile = trim((string)(isset($row["mobile"]) ? $row["mobile"] : ""));
    $guardianPhone = trim((string)(isset($row["nextofkin_contact"]) ? $row["nextofkin_contact"] : ""));
    if ($mobile !== "" || $guardianPhone !== "") {
        $reachableCount++;
    } else {
        $missingContactCount++;
    }
}

$pageMessage = isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"] = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/enablesmsalert.css">
<script src="scripts/enablesmsalert.js" defer></script>
</head>
<body class="sms-alert-page">
<div class="header">
<?php include("menu.php"); ?>
</div>

<main class="main-platform sms-alert-shell">
    <section class="sms-alert-hero">
        <div class="sms-alert-hero__copy">
            <span class="sms-alert-kicker"><i class="fa fa-phone"></i> SMS Alert Settings</span>
            <h1>Manage Student SMS Alerts</h1>
            <p>Select a class, review contact coverage, and turn SMS alerts on or off for the students in that list.</p>
            <div class="sms-alert-hero__chips">
                <span class="sms-alert-chip"><i class="fa fa-building"></i> <?php echo $selectedClassLabel !== "" ? sms_alert_esc($selectedClassLabel) : "No class selected"; ?></span>
                <span class="sms-alert-chip"><i class="fa fa-users"></i> <?php echo number_format((int)count($classOptions)); ?> class<?php echo count($classOptions) === 1 ? "" : "es"; ?> available</span>
            </div>
        </div>

        <div class="sms-alert-stats">
            <article class="sms-alert-stat">
                <span>Students</span>
                <strong><?php echo number_format((int)$totalStudents); ?></strong>
                <small><?php echo $selectedClassLabel !== "" ? "Students currently listed for this class." : "Choose a class to load students."; ?></small>
            </article>
            <article class="sms-alert-stat">
                <span>SMS Enabled</span>
                <strong><?php echo number_format((int)$enabledCount); ?></strong>
                <small>Students who are currently marked to receive SMS alerts.</small>
            </article>
            <article class="sms-alert-stat">
                <span>Reachable Contacts</span>
                <strong><?php echo number_format((int)$reachableCount); ?></strong>
                <small>Students with a mobile number or guardian contact on file.</small>
            </article>
            <article class="sms-alert-stat sms-alert-stat--accent">
                <span>Missing Contacts</span>
                <strong><?php echo number_format((int)$missingContactCount); ?></strong>
                <small>Students who still need a usable phone number for SMS delivery.</small>
            </article>
        </div>
    </section>

    <?php if ($pageMessage !== "") { ?>
    <div class="sms-alert-message-stack"><?php echo $pageMessage; ?></div>
    <?php } ?>

    <section class="sms-alert-surface">
        <div class="sms-alert-panel-head">
            <div>
                <span class="sms-alert-panel-kicker">Class Filter</span>
                <h2>Choose a class</h2>
                <p>Load one class at a time, then review and save the SMS alert status for the students in that class.</p>
            </div>
        </div>

        <form method="get" action="enablesmsalert.php" class="sms-alert-filter-form">
            <label class="sms-alert-field" for="class_entryid">
                <span>Class</span>
                <select id="class_entryid" name="class_entryid" required>
                    <option value="">Select Class</option>
                    <?php foreach ($classOptions as $classOption) { ?>
                    <option value="<?php echo sms_alert_esc($classOption["class_entryid"]); ?>"<?php echo $selectedClassId === trim((string)$classOption["class_entryid"]) ? " selected" : ""; ?>>
                        <?php echo sms_alert_esc($classOption["class_name"]); ?>
                    </option>
                    <?php } ?>
                </select>
            </label>

            <div class="sms-alert-filter-actions">
                <button class="sms-alert-btn sms-alert-btn--primary" type="submit"><i class="fa fa-search"></i> Show Students</button>
                <a class="sms-alert-btn sms-alert-btn--secondary" href="enablesmsalert.php"><i class="fa fa-undo"></i> Clear</a>
            </div>
        </form>
    </section>

    <?php if ($selectedClassId === "") { ?>
    <section class="sms-alert-surface sms-alert-empty-state">
        <i class="fa fa-filter"></i>
        <h3>Select a class to begin</h3>
        <p>Once you choose a class, this page will show each student’s current SMS alert setting and contact status.</p>
    </section>
    <?php } else { ?>
        <section class="sms-alert-surface">
            <div class="sms-alert-panel-head">
                <div>
                    <span class="sms-alert-panel-kicker">Student List</span>
                    <h2><?php echo sms_alert_esc($selectedClassLabel !== "" ? $selectedClassLabel : "Selected Class"); ?></h2>
                    <p>Checked students will remain on SMS alerts when you save. Unchecked students in this list will be turned off.</p>
                </div>
                <span class="sms-alert-panel-tag"><i class="fa fa-list"></i> <?php echo number_format((int)$totalStudents); ?> student<?php echo $totalStudents === 1 ? "" : "s"; ?></span>
            </div>

            <?php if ($totalStudents > 0) { ?>
            <form method="post" action="enablesmsalert.php?class_entryid=<?php echo urlencode($selectedClassId); ?>" class="sms-alert-form">
                <input type="hidden" name="class_entryid" value="<?php echo sms_alert_esc($selectedClassId); ?>">

                <div class="sms-alert-toolbar">
                    <label class="sms-alert-search">
                        <i class="fa fa-search"></i>
                        <input type="search" placeholder="Search student name, ID, username, or contact" data-student-search>
                    </label>
                    <div class="sms-alert-toolbar__actions">
                        <button type="button" class="sms-alert-btn sms-alert-btn--secondary sms-alert-btn--inline" data-select-visible><i class="fa fa-check-square-o"></i> Select Visible</button>
                        <button type="button" class="sms-alert-btn sms-alert-btn--secondary sms-alert-btn--inline" data-clear-visible><i class="fa fa-square-o"></i> Clear Visible</button>
                    </div>
                </div>

                <div class="sms-alert-selection-meta">
                    <span><strong data-selected-count><?php echo number_format((int)$enabledCount); ?></strong> selected</span>
                    <span><strong data-visible-count><?php echo number_format((int)$totalStudents); ?></strong> visible</span>
                    <span>Unchecked visible: <strong data-disabled-count><?php echo number_format((int)$disabledCount); ?></strong></span>
                </div>

                <div class="sms-alert-table-wrap">
                    <table class="sms-alert-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Contact</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th class="sms-alert-table__check">
                                    Alert
                                    <input type="checkbox" data-toggle-all aria-label="Select all visible students">
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentRows as $index => $row) {
                                $studentName = sms_alert_student_name($row);
                                $mobile = trim((string)(isset($row["mobile"]) ? $row["mobile"] : ""));
                                $guardianPhone = trim((string)(isset($row["nextofkin_contact"]) ? $row["nextofkin_contact"] : ""));
                                $username = trim((string)(isset($row["username"]) ? $row["username"] : ""));
                                if ($username === "") {
                                    $username = trim((string)$row["userid"]);
                                }
                                $smsEnabled = (int)(isset($row["smsalert"]) ? $row["smsalert"] : 0) === 1;
                                $searchText = strtolower(trim($studentName . " " . $row["userid"] . " " . $username . " " . $mobile . " " . $guardianPhone));
                            ?>
                            <tr data-student-row data-search="<?php echo sms_alert_esc($searchText); ?>">
                                <td data-label="#"><?php echo (int)($index + 1); ?></td>
                                <td data-label="Student">
                                    <div class="sms-alert-student">
                                        <strong><?php echo sms_alert_esc($studentName); ?></strong>
                                        <small><?php echo sms_alert_esc($row["userid"]); ?></small>
                                    </div>
                                </td>
                                <td data-label="Contact">
                                    <div class="sms-alert-contact">
                                        <?php if ($mobile !== "") { ?>
                                        <strong><?php echo sms_alert_esc($mobile); ?></strong>
                                        <small>Student Mobile</small>
                                        <?php } elseif ($guardianPhone !== "") { ?>
                                        <strong><?php echo sms_alert_esc($guardianPhone); ?></strong>
                                        <small>Guardian Contact</small>
                                        <?php } else { ?>
                                        <strong>No contact</strong>
                                        <small>SMS cannot be delivered</small>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td data-label="Username"><span class="sms-alert-username"><?php echo sms_alert_esc($username); ?></span></td>
                                <td data-label="Status">
                                    <?php if ($smsEnabled) { ?>
                                    <span class="sms-alert-badge sms-alert-badge--enabled">Enabled</span>
                                    <?php } else { ?>
                                    <span class="sms-alert-badge sms-alert-badge--disabled">Disabled</span>
                                    <?php } ?>
                                </td>
                                <td data-label="Alert" class="sms-alert-table__check">
                                    <label class="sms-alert-check">
                                        <input type="checkbox" name="smscheck[]" value="<?php echo sms_alert_esc($row["userid"]); ?>" data-student-checkbox<?php echo $smsEnabled ? " checked" : ""; ?>>
                                        <span>Include</span>
                                    </label>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="sms-alert-empty-state sms-alert-empty-state--inline" data-search-empty hidden>
                    <i class="fa fa-search"></i>
                    <h3>No students match this search</h3>
                    <p>Try another student name, ID, username, or contact number.</p>
                </div>

                <div class="sms-alert-save-bar">
                    <div class="sms-alert-save-bar__copy">
                        <strong>Save SMS alert settings</strong>
                        <span>The checked list will be kept on SMS alerts after saving.</span>
                    </div>
                    <button class="sms-alert-btn sms-alert-btn--primary" type="submit" name="save_sms_alerts"><i class="fa fa-save"></i> Save SMS Alert Settings</button>
                </div>
            </form>
            <?php } else { ?>
            <div class="sms-alert-empty-state">
                <i class="fa fa-users"></i>
                <h3>No students found</h3>
                <p>The selected class does not currently have active student records to manage.</p>
            </div>
            <?php } ?>
        </section>
    <?php } ?>
</main>
</body>
</html>
