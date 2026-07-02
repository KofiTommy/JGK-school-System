<?php
session_start();

include("dbstring.php");
include("check-login.php");
include_once("house-master-utils.php");
include("gradingsystem.php");

if (!function_exists("sms_report_esc")) {
    function sms_report_esc($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("sms_report_flash_html")) {
    function sms_report_flash_html($tone, $message)
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

        return "<div class='sms-report-flash sms-report-flash--" . $tone . "'><span class='sms-report-flash__icon'><i class='fa " . $icon . "'></i></span><div class='sms-report-flash__body'>" . $message . "</div></div>";
    }
}

if (!function_exists("sms_report_student_name")) {
    function sms_report_student_name($row)
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

if (!function_exists("sms_report_batch_options")) {
    function sms_report_batch_options($con)
    {
        $rows = array();
        $sql = mysqli_query($con, "SELECT batchid,batch,status,datetimeentry FROM tblbatch ORDER BY datetimeentry DESC, batch DESC");
        if ($sql) {
            while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists("sms_report_batch_label")) {
    function sms_report_batch_label($batchOptions, $batchId)
    {
        $batchId = trim((string)$batchId);
        foreach ($batchOptions as $row) {
            if ($batchId !== "" && $batchId === trim((string)$row["batchid"])) {
                return trim((string)$row["batch"]);
            }
        }
        return "";
    }
}

if (!function_exists("sms_report_student_rows")) {
    function sms_report_student_rows($con, $batchId)
    {
        $batchId = trim((string)$batchId);
        if ($batchId === "") {
            return array();
        }

        $batchIdSafe = mysqli_real_escape_string($con, $batchId);
        $rows = array();
        $sql = mysqli_query($con, "SELECT
                su.userid,
                su.firstname,
                su.surname,
                su.othernames,
                su.mobile,
                su.email,
                su.username,
                sr.examresults,
                sr.entrydatetime AS report_datetime
            FROM tblsystemuser su
            INNER JOIN (
                SELECT DISTINCT userid
                FROM tblstudentterminalreport
                WHERE batchid='$batchIdSafe'
            ) tr ON su.userid=tr.userid
            LEFT JOIN tblsmsexamresults sr ON sr.userid=su.userid AND sr.batchid='$batchIdSafe'
            WHERE su.systemtype='Student'
            ORDER BY su.firstname ASC, su.othernames ASC, su.surname ASC, su.userid ASC");
        if ($sql) {
            while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists("sms_report_generate_id")) {
    function sms_report_generate_id()
    {
        $prefix = "";
        if (isset($_SESSION["BRANCHID"]) && isset($_SESSION["USERID"])) {
            $prefix = "SMS";
        }
        return $prefix . date("YmdHis") . "_" . substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 10);
    }
}

if (!function_exists("sms_report_build_message")) {
    function sms_report_build_message($con, $studentId, $batchId, $gradeObj)
    {
        $studentIdSafe = mysqli_real_escape_string($con, trim((string)$studentId));
        $batchIdSafe = mysqli_real_escape_string($con, trim((string)$batchId));
        if ($studentIdSafe === "" || $batchIdSafe === "") {
            return array("batch" => "", "message" => "", "subject_count" => 0);
        }

        $subjectParts = array();
        $batchLabel = "";
        $sql = mysqli_query($con, "SELECT
                sa.assignmentid,
                sub.subject,
                bh.batch,
                MAX(CASE WHEN mk.testtype='Exam Score' THEN mk.mark END) AS exam_score,
                MAX(CASE WHEN mk.testtype='Class Score' THEN mk.mark END) AS class_score
            FROM tblmark mk
            INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
            INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
            INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
            INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid
            INNER JOIN tblbatch bh ON sa.batchid=bh.batchid
            WHERE mk.userid='$studentIdSafe'
              AND sa.batchid='$batchIdSafe'
            GROUP BY sa.assignmentid, sub.subject, bh.batch
            ORDER BY sub.subject ASC");

        if ($sql) {
            while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
                $batchLabel = trim((string)$row["batch"]);
                $examScore = (float)(isset($row["exam_score"]) ? $row["exam_score"] : 0);
                $classScore = (float)(isset($row["class_score"]) ? $row["class_score"] : 0);
                $totalScore = $examScore + $classScore;
                $gradeObj->setMark($totalScore);
                $finalGrade = trim((string)$gradeObj->getMark());
                $subject = trim((string)$row["subject"]);
                if ($subject !== "" && $finalGrade !== "") {
                    $subjectParts[] = $subject . "-" . $finalGrade;
                }
            }
        }

        if (empty($subjectParts) || $batchLabel === "") {
            return array("batch" => $batchLabel, "message" => "", "subject_count" => 0);
        }

        return array(
            "batch" => $batchLabel,
            "message" => $batchLabel . ":" . implode(",", $subjectParts) . ".",
            "subject_count" => count($subjectParts)
        );
    }
}

if (!function_exists("sms_report_upsert_result")) {
    function sms_report_upsert_result($con, $studentId, $phone, $batchId, $message, $recordedBy)
    {
        $studentId = trim((string)$studentId);
        $phone = trim((string)$phone);
        $batchId = trim((string)$batchId);
        $message = trim((string)$message);
        $recordedBy = trim((string)$recordedBy);
        if ($studentId === "" || $batchId === "" || $message === "") {
            return false;
        }

        $studentIdSafe = mysqli_real_escape_string($con, $studentId);
        $batchIdSafe = mysqli_real_escape_string($con, $batchId);
        $phoneSafe = mysqli_real_escape_string($con, $phone);
        $messageSafe = mysqli_real_escape_string($con, $message);
        $recordedBySafe = mysqli_real_escape_string($con, $recordedBy);

        $checkSql = mysqli_query($con, "SELECT smsexamid FROM tblsmsexamresults WHERE userid='$studentIdSafe' AND batchid='$batchIdSafe' LIMIT 1");
        if ($checkSql && $row = mysqli_fetch_array($checkSql, MYSQLI_ASSOC)) {
            $reportIdSafe = mysqli_real_escape_string($con, (string)$row["smsexamid"]);
            return mysqli_query($con, "UPDATE tblsmsexamresults
                SET mobile='$phoneSafe', examresults='$messageSafe', status='active', entrydatetime=NOW(), recordedby='$recordedBySafe'
                WHERE smsexamid='$reportIdSafe'")
                ? true
                : false;
        }

        $reportId = sms_report_generate_id();
        $reportIdSafe = mysqli_real_escape_string($con, $reportId);
        return mysqli_query($con, "INSERT INTO tblsmsexamresults(smsexamid,userid,mobile,batchid,status,examresults,entrydatetime,recordedby)
            VALUES('$reportIdSafe','$studentIdSafe','$phoneSafe','$batchIdSafe','active','$messageSafe',NOW(),'$recordedBySafe')")
            ? true
            : false;
    }
}

$selectedBatchId = "";
if (isset($_GET["batchid"])) {
    $selectedBatchId = trim((string)$_GET["batchid"]);
} elseif (isset($_POST["batchid"])) {
    $selectedBatchId = trim((string)$_POST["batchid"]);
} elseif (isset($_POST["batch_selected_id"])) {
    $selectedBatchId = trim((string)$_POST["batch_selected_id"]);
}

if (isset($_POST["sendsms"])) {
    $selectedBatchId = isset($_POST["batch_selected_id"]) ? trim((string)$_POST["batch_selected_id"]) : $selectedBatchId;
    $selectedUsers = isset($_POST["userid"]) && is_array($_POST["userid"]) ? $_POST["userid"] : array();
    if ($selectedBatchId === "") {
        $_SESSION["Message"] = sms_report_flash_html("error", "Select a batch before sending exam result SMS messages.");
        header("location:smsreport.php");
        exit();
    }

    if (empty($selectedUsers)) {
        $_SESSION["Message"] = sms_report_flash_html("error", "No students were selected.");
        header("location:smsreport.php?batchid=" . urlencode($selectedBatchId));
        exit();
    }

    $allowedRows = sms_report_student_rows($con, $selectedBatchId);
    $allowedMap = array();
    foreach ($allowedRows as $row) {
        $allowedMap[trim((string)$row["userid"])] = $row;
    }

    $gradeObj = new GradingSystem();
    $recordedBy = isset($_SESSION["USERID"]) ? trim((string)$_SESSION["USERID"]) : "";
    $sentCount = 0;
    $failedCount = 0;
    $noMobileCount = 0;
    $noResultCount = 0;
    $storedCount = 0;

    foreach ($selectedUsers as $rawUserId) {
        $studentId = trim((string)$rawUserId);
        if ($studentId === "" || !isset($allowedMap[$studentId])) {
            continue;
        }

        $studentRow = $allowedMap[$studentId];
        $mobile = trim((string)(isset($studentRow["mobile"]) ? $studentRow["mobile"] : ""));
        if ($mobile === "") {
            $noMobileCount++;
            continue;
        }

        $messageData = sms_report_build_message($con, $studentId, $selectedBatchId, $gradeObj);
        if (trim((string)$messageData["message"]) === "") {
            $noResultCount++;
            continue;
        }

        if (sms_report_upsert_result($con, $studentId, $mobile, $selectedBatchId, $messageData["message"], $recordedBy)) {
            $storedCount++;
        }

        $smsCode = "";
        $sentOk = function_exists("send_bulk_sms_message")
            ? send_bulk_sms_message($mobile, $messageData["message"], $smsCode)
            : false;

        if ($sentOk) {
            $sentCount++;
        } else {
            $failedCount++;
        }
    }

    $summaryParts = array();
    $summaryParts[] = "Sent: <strong>" . number_format((int)$sentCount) . "</strong>";
    if ($failedCount > 0) {
        $summaryParts[] = "Failed: <strong>" . number_format((int)$failedCount) . "</strong>";
    }
    if ($noMobileCount > 0) {
        $summaryParts[] = "No mobile: <strong>" . number_format((int)$noMobileCount) . "</strong>";
    }
    if ($noResultCount > 0) {
        $summaryParts[] = "No results: <strong>" . number_format((int)$noResultCount) . "</strong>";
    }
    if ($storedCount > 0) {
        $summaryParts[] = "Saved reports: <strong>" . number_format((int)$storedCount) . "</strong>";
    }

    $tone = ($sentCount > 0 && $failedCount === 0 && $noMobileCount === 0 && $noResultCount === 0) ? "success" : (($sentCount > 0) ? "warning" : "error");
    $_SESSION["Message"] = sms_report_flash_html($tone, "SMS processing completed. " . implode(" | ", $summaryParts) . ".");
    header("location:smsreport.php?batchid=" . urlencode($selectedBatchId));
    exit();
}

$batchOptions = sms_report_batch_options($con);
$selectedBatchLabel = sms_report_batch_label($batchOptions, $selectedBatchId);
$studentRows = array();
if ($selectedBatchId !== "") {
    $studentRows = sms_report_student_rows($con, $selectedBatchId);
}

$totalStudents = count($studentRows);
$studentsWithMobile = 0;
$studentsWithoutMobile = 0;
$savedReportCount = 0;
foreach ($studentRows as $row) {
    $mobile = trim((string)(isset($row["mobile"]) ? $row["mobile"] : ""));
    if ($mobile !== "") {
        $studentsWithMobile++;
    } else {
        $studentsWithoutMobile++;
    }
    if (trim((string)(isset($row["examresults"]) ? $row["examresults"] : "")) !== "") {
        $savedReportCount++;
    }
}

$pageMessage = isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"] = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/smsreport.css">
<script src="scripts/smsreport.js" defer></script>
</head>
<body class="sms-report-page">
<div class="header">
<?php include("menu.php"); ?>
</div>

<main class="main-platform sms-report-shell">
    <section class="sms-report-hero">
        <div class="sms-report-hero__copy">
            <span class="sms-report-kicker"><i class="fa fa-envelope"></i> Exam Results SMS</span>
            <h1>Send Exam Results By Batch</h1>
            <p>Select a batch, review the student list, and send the saved exam-grade summary by SMS to the students with mobile numbers on record.</p>
            <div class="sms-report-hero__chips">
                <span class="sms-report-chip"><i class="fa fa-calendar"></i> <?php echo $selectedBatchLabel !== "" ? sms_report_esc($selectedBatchLabel) : "No batch selected"; ?></span>
                <span class="sms-report-chip"><i class="fa fa-database"></i> <?php echo number_format((int)count($batchOptions)); ?> batch<?php echo count($batchOptions) === 1 ? "" : "es"; ?> available</span>
            </div>
        </div>

        <div class="sms-report-stats">
            <article class="sms-report-stat">
                <span>Students</span>
                <strong><?php echo number_format((int)$totalStudents); ?></strong>
                <small><?php echo $selectedBatchLabel !== "" ? "Students listed under the selected batch." : "Choose a batch to load students."; ?></small>
            </article>
            <article class="sms-report-stat">
                <span>With Mobile</span>
                <strong><?php echo number_format((int)$studentsWithMobile); ?></strong>
                <small>Students who can receive SMS directly from this page.</small>
            </article>
            <article class="sms-report-stat">
                <span>Saved Reports</span>
                <strong><?php echo number_format((int)$savedReportCount); ?></strong>
                <small>Students who already have generated exam-result SMS data for this batch.</small>
            </article>
            <article class="sms-report-stat sms-report-stat--accent">
                <span>Missing Mobile</span>
                <strong><?php echo number_format((int)$studentsWithoutMobile); ?></strong>
                <small>Students who cannot receive SMS until a mobile number is added.</small>
            </article>
        </div>
    </section>

    <?php if ($pageMessage !== "") { ?>
    <div class="sms-report-message-stack"><?php echo $pageMessage; ?></div>
    <?php } ?>

    <section class="sms-report-surface">
        <div class="sms-report-panel-head">
            <div>
                <span class="sms-report-panel-kicker">Batch Filter</span>
                <h2>Choose a batch</h2>
                <p>Load one batch at a time, then select the students you want to send exam result summaries to.</p>
            </div>
        </div>

        <form method="get" action="smsreport.php" class="sms-report-filter-form">
            <label class="sms-report-field" for="batchid">
                <span>Batch</span>
                <select id="batchid" name="batchid" required>
                    <option value="">Select Batch</option>
                    <?php foreach ($batchOptions as $batchOption) { ?>
                    <option value="<?php echo sms_report_esc($batchOption["batchid"]); ?>"<?php echo $selectedBatchId === trim((string)$batchOption["batchid"]) ? " selected" : ""; ?>>
                        <?php echo sms_report_esc($batchOption["batch"]); ?><?php echo trim((string)$batchOption["status"]) !== "" ? " (" . sms_report_esc($batchOption["status"]) . ")" : ""; ?>
                    </option>
                    <?php } ?>
                </select>
            </label>

            <div class="sms-report-filter-actions">
                <button class="sms-report-btn sms-report-btn--primary" type="submit"><i class="fa fa-search"></i> Show Students</button>
                <a class="sms-report-btn sms-report-btn--secondary" href="smsreport.php"><i class="fa fa-undo"></i> Clear</a>
            </div>
        </form>
    </section>

    <?php if ($selectedBatchId === "") { ?>
    <section class="sms-report-surface sms-report-empty-state">
        <i class="fa fa-filter"></i>
        <h3>Select a batch to begin</h3>
        <p>Once you choose a batch, this page will show the students in that batch and let you send their exam result summaries by SMS.</p>
    </section>
    <?php } else { ?>
        <section class="sms-report-surface">
            <div class="sms-report-panel-head">
                <div>
                    <span class="sms-report-panel-kicker">Student List</span>
                    <h2><?php echo sms_report_esc($selectedBatchLabel !== "" ? $selectedBatchLabel : "Selected Batch"); ?></h2>
                    <p>Select the students you want to message. The system will generate the subject-grade summary for the selected batch and send it to the mobile number saved on the student record.</p>
                </div>
                <span class="sms-report-panel-tag"><i class="fa fa-list"></i> <?php echo number_format((int)$totalStudents); ?> student<?php echo $totalStudents === 1 ? "" : "s"; ?></span>
            </div>

            <?php if ($totalStudents > 0) { ?>
            <form method="post" action="smsreport.php?batchid=<?php echo urlencode($selectedBatchId); ?>" class="sms-report-form">
                <input type="hidden" name="batch_selected_id" value="<?php echo sms_report_esc($selectedBatchId); ?>">

                <div class="sms-report-toolbar">
                    <label class="sms-report-search">
                        <i class="fa fa-search"></i>
                        <input type="search" placeholder="Search student name, ID, mobile, email, or username" data-student-search>
                    </label>
                    <div class="sms-report-toolbar__actions">
                        <button type="button" class="sms-report-btn sms-report-btn--secondary sms-report-btn--inline" data-select-visible><i class="fa fa-check-square-o"></i> Select Visible</button>
                        <button type="button" class="sms-report-btn sms-report-btn--secondary sms-report-btn--inline" data-clear-visible><i class="fa fa-square-o"></i> Clear Visible</button>
                    </div>
                </div>

                <div class="sms-report-selection-meta">
                    <span><strong data-selected-count>0</strong> selected</span>
                    <span><strong data-visible-count><?php echo number_format((int)$totalStudents); ?></strong> visible</span>
                    <span>Ready with mobile: <strong><?php echo number_format((int)$studentsWithMobile); ?></strong></span>
                </div>

                <div class="sms-report-table-wrap">
                    <table class="sms-report-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Contact</th>
                                <th>Username</th>
                                <th>Saved SMS Data</th>
                                <th class="sms-report-table__check">
                                    Send
                                    <input type="checkbox" data-toggle-all aria-label="Select all visible students">
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentRows as $index => $row) {
                                $studentName = sms_report_student_name($row);
                                $mobile = trim((string)(isset($row["mobile"]) ? $row["mobile"] : ""));
                                $email = trim((string)(isset($row["email"]) ? $row["email"] : ""));
                                $username = trim((string)(isset($row["username"]) ? $row["username"] : ""));
                                if ($username === "") {
                                    $username = trim((string)$row["userid"]);
                                }
                                $savedReport = trim((string)(isset($row["examresults"]) ? $row["examresults"] : ""));
                                $savedAt = trim((string)(isset($row["report_datetime"]) ? $row["report_datetime"] : ""));
                                $searchText = strtolower(trim($studentName . " " . $row["userid"] . " " . $mobile . " " . $email . " " . $username . " " . $savedReport));
                            ?>
                            <tr data-student-row data-search="<?php echo sms_report_esc($searchText); ?>">
                                <td data-label="#"><?php echo (int)($index + 1); ?></td>
                                <td data-label="Student">
                                    <div class="sms-report-student">
                                        <strong><?php echo sms_report_esc($studentName); ?></strong>
                                        <small><?php echo sms_report_esc($row["userid"]); ?></small>
                                    </div>
                                </td>
                                <td data-label="Contact">
                                    <div class="sms-report-contact">
                                        <?php if ($mobile !== "") { ?>
                                        <strong><?php echo sms_report_esc($mobile); ?></strong>
                                        <small><?php echo $email !== "" ? sms_report_esc($email) : "No email on file"; ?></small>
                                        <?php } else { ?>
                                        <strong>No mobile</strong>
                                        <small><?php echo $email !== "" ? sms_report_esc($email) : "No email on file"; ?></small>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td data-label="Username"><span class="sms-report-username"><?php echo sms_report_esc($username); ?></span></td>
                                <td data-label="Saved SMS Data">
                                    <?php if ($savedReport !== "") { ?>
                                    <div class="sms-report-status-block">
                                        <span class="sms-report-badge sms-report-badge--saved">Saved</span>
                                        <small><?php echo $savedAt !== "" ? sms_report_esc($savedAt) : "Previously generated"; ?></small>
                                    </div>
                                    <?php } else { ?>
                                    <div class="sms-report-status-block">
                                        <span class="sms-report-badge sms-report-badge--fresh">Not saved yet</span>
                                        <small>Will be generated when you send.</small>
                                    </div>
                                    <?php } ?>
                                </td>
                                <td data-label="Send" class="sms-report-table__check">
                                    <label class="sms-report-check">
                                        <input type="checkbox" name="userid[]" value="<?php echo sms_report_esc($row["userid"]); ?>" data-student-checkbox>
                                        <span>Include</span>
                                    </label>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="sms-report-empty-state sms-report-empty-state--inline" data-search-empty hidden>
                    <i class="fa fa-search"></i>
                    <h3>No students match this search</h3>
                    <p>Try another student name, ID, mobile number, email, or username.</p>
                </div>

                <div class="sms-report-save-bar">
                    <div class="sms-report-save-bar__copy">
                        <strong>Send selected SMS messages</strong>
                        <span>Each selected student will receive the exam result summary for the chosen batch using the mobile number saved on the student record.</span>
                    </div>
                    <button class="sms-report-btn sms-report-btn--primary" type="submit" name="sendsms"><i class="fa fa-paper-plane"></i> Send Exam Result SMS</button>
                </div>
            </form>
            <?php } else { ?>
            <div class="sms-report-empty-state">
                <i class="fa fa-users"></i>
                <h3>No students found</h3>
                <p>The selected batch does not currently have student terminal report records to use for SMS reporting.</p>
            </div>
            <?php } ?>
        </section>
    <?php } ?>
</main>
</body>
</html>
