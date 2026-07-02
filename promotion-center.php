<?php
session_start();
$_SESSION['Message'] = "";
include("dbstring.php");
include("check-login.php");

if (!(isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL'] == "administrator") && !(function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user())) {
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php"));
    exit();
}
if (
    !(function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user()) &&
    (!isset($_SESSION['SYSTEMTYPE']) || ($_SESSION['SYSTEMTYPE'] != "normal_user" && $_SESSION['SYSTEMTYPE'] != "super_user"))
) {
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php"));
    exit();
}

function flushResults($con) {
    while (mysqli_more_results($con) && mysqli_next_result($con)) {}
}

function callPromoteStudent($con, $userid, $fromBatch, $toBatch, $toClass, $recordedBy, $reason) {
    $stmt = mysqli_prepare($con, "CALL sp_promote_student(?,?,?,?,?,?)");
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ssssss", $userid, $fromBatch, $toBatch, $toClass, $recordedBy, $reason);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    flushResults($con);
    return $ok;
}

function getPromotionResultSummary($con, $recordedBy, $startTs) {
    $summary = array("total" => 0, "promoted" => 0, "skipped" => 0);
    $recordedBySafe = mysqli_real_escape_string($con, $recordedBy);
    $startTsSafe = mysqli_real_escape_string($con, $startTs);
    $sql = "SELECT status, COUNT(*) AS cnt
            FROM tblstudentpromotion
            WHERE promoted_by='$recordedBySafe' AND promoted_on >= '$startTsSafe'
            GROUP BY status";
    $rs = mysqli_query($con, $sql);
    if ($rs) {
        while ($row = mysqli_fetch_array($rs, MYSQLI_ASSOC)) {
            $summary["total"] += intval($row["cnt"]);
            if ($row["status"] == "promoted") {
                $summary["promoted"] = intval($row["cnt"]);
            }
            if ($row["status"] == "skipped") {
                $summary["skipped"] = intval($row["cnt"]);
            }
        }
    }
    return $summary;
}

function countActiveClassStudents($con, $classId, $batchId) {
    $classSafe = mysqli_real_escape_string($con, $classId);
    $batchSafe = mysqli_real_escape_string($con, $batchId);
    $sql = "SELECT COUNT(*) AS cnt FROM tblclass WHERE class_entryid='$classSafe' AND batchid='$batchSafe' AND status='active'";
    $rs = mysqli_query($con, $sql);
    if ($rs && $row = mysqli_fetch_array($rs, MYSQLI_ASSOC)) {
        return intval($row["cnt"]);
    }
    return 0;
}

function getActiveStudentIdsForClassBatch($con, $classId, $batchId) {
    $ids = array();
    $classSafe = mysqli_real_escape_string($con, $classId);
    $batchSafe = mysqli_real_escape_string($con, $batchId);
    $sql = "SELECT userid FROM tblclass
            WHERE class_entryid='$classSafe' AND batchid='$batchSafe' AND status='active'";
    $rs = mysqli_query($con, $sql);
    if ($rs) {
        while ($row = mysqli_fetch_array($rs, MYSQLI_ASSOC)) {
            $ids[] = $row["userid"];
        }
    }
    return $ids;
}

function getDryRunRows($con, $fromBatch, $toBatch) {
    $rows = array();
    if ($fromBatch == "" || $toBatch == "") {
        return $rows;
    }
    $fromBatchSafe = mysqli_real_escape_string($con, $fromBatch);
    $toBatchSafe = mysqli_real_escape_string($con, $toBatch);

    $sql = "SELECT vc.userid, vc.firstname, vc.surname, vc.othernames, vc.from_batch, vc.class_name, vc.to_class_name, vc.note,
                   CASE WHEN tc.classid IS NULL THEN 'READY' ELSE 'SKIP (ALREADY IN TARGET BATCH)' END AS dry_run_status
            FROM vw_terminal_promotion_candidates vc
            LEFT JOIN tblclass tc ON tc.userid = vc.userid AND tc.batchid='$toBatchSafe'
            WHERE vc.from_batchid='$fromBatchSafe'
            ORDER BY vc.class_name ASC, vc.userid ASC";
    $rs = mysqli_query($con, $sql);
    if ($rs) {
        while ($row = mysqli_fetch_array($rs, MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

@$_PromotionType = $_POST["promotion_type"];
@$_UserId = $_POST["userid"];
@$_FromClass = $_POST["from_classid"];
@$_ToClass = $_POST["to_classid"];
@$_FromBatch = $_POST["from_batchid"];
@$_ToBatch = $_POST["to_batchid"];
@$_Reason = trim($_POST["reason"]);
@$_RecordedBy = $_SESSION["USERID"];
if ($_Reason == "") {
    $_Reason = "Year-end promotion";
}
$_DryRunRows = array();

if (isset($_POST["run_dry_run"])) {
    if ($_FromBatch == "" || $_ToBatch == "") {
        $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Select both From Batch and To Batch to preview auto promotion.</div>";
    } else {
        $_DryRunRows = getDryRunRows($con, $_FromBatch, $_ToBatch);
        $_SESSION['Message'] = "<div style='color:#0b63ce;padding:8px;border:1px solid #bcd;background:#eef6ff;text-align:center;'>Dry run generated: ".count($_DryRunRows)." record(s). No data changed.</div>";
    }
}

if (isset($_POST["export_dry_run_csv"])) {
    if ($_FromBatch == "" || $_ToBatch == "") {
        $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Select both From Batch and To Batch before exporting preview.</div>";
    } else {
        $_DryRunRows = getDryRunRows($con, $_FromBatch, $_ToBatch);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=dry_run_promotion_preview_'.date('Ymd_His').'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('userid', 'student_name', 'from_batch', 'current_class', 'suggested_class', 'terminal_note', 'dry_run_status'));
        foreach ($_DryRunRows as $r) {
            $name = trim($r['firstname']." ".$r['othernames']." ".$r['surname']);
            fputcsv($out, array($r['userid'], $name, $r['from_batch'], $r['class_name'], $r['to_class_name'], $r['note'], $r['dry_run_status']));
        }
        fclose($out);
        exit();
    }
}

if (isset($_POST["run_promotion"])) {
    if ($_PromotionType == "single") {
        if ($_UserId == "" || $_FromBatch == "" || $_ToBatch == "" || $_ToClass == "") {
            $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Complete all required fields for single student promotion.</div>";
        } else if ($_FromBatch == $_ToBatch && $_FromClass != "" && $_FromClass == $_ToClass) {
            $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>From Class and To Class cannot be the same for same-batch promotion.</div>";
        } else {
            $_StartTs = date("Y-m-d H:i:s");
            $ok = callPromoteStudent($con, $_UserId, $_FromBatch, $_ToBatch, $_ToClass, $_RecordedBy, $_Reason);
            if ($ok) {
                $_Summary = getPromotionResultSummary($con, $_RecordedBy, $_StartTs);
                if ($_Summary["total"] > 0) {
                    $_SESSION['Message'] = "<div style='color:green;padding:8px;border:1px solid #aea;background:#efe;text-align:center;'>Single promotion completed. Promoted: ".$_Summary["promoted"].", Skipped: ".$_Summary["skipped"].".</div>";
                } else {
                    $_SESSION['Message'] = "<div style='color:#0b63ce;padding:8px;border:1px solid #bcd;background:#eef6ff;text-align:center;'>Procedure executed but no promotion log was written. Check inputs and source class state.</div>";
                }
            } else {
                $_Error = mysqli_error($con);
                $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Promotion failed. $_Error</div>";
            }
        }
    } else {
        if ($_FromClass == "" || $_ToClass == "" || $_FromBatch == "" || $_ToBatch == "") {
            $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Complete all required fields for class promotion.</div>";
        } else if ($_FromBatch == $_ToBatch && $_FromClass == $_ToClass) {
            $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>From Class and To Class cannot be the same for same-batch promotion.</div>";
        } else if (countActiveClassStudents($con, $_FromClass, $_FromBatch) == 0) {
            $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>No active students found in selected From Class and From Batch.</div>";
        } else {
            $_StartTs = date("Y-m-d H:i:s");
            $studentIds = getActiveStudentIdsForClassBatch($con, $_FromClass, $_FromBatch);
            if (count($studentIds) == 0) {
                $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>No active students found in selected From Class and From Batch.</div>";
            } else {
                $hadError = false;
                foreach ($studentIds as $sid) {
                    $ok = callPromoteStudent($con, $sid, $_FromBatch, $_ToBatch, $_ToClass, $_RecordedBy, $_Reason);
                    if (!$ok) {
                        $hadError = true;
                        break;
                    }
                }
                if ($hadError) {
                    $_Error = mysqli_error($con);
                    $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Class promotion failed. $_Error</div>";
                } else {
                    $_Summary = getPromotionResultSummary($con, $_RecordedBy, $_StartTs);
                    $_SESSION['Message'] = "<div style='color:green;padding:8px;border:1px solid #aea;background:#efe;text-align:center;'>Class promotion completed. Teacher subject assignments were not auto-copied. Promoted: ".$_Summary["promoted"].", Skipped: ".$_Summary["skipped"].".</div>";
                }
            }
        }
    }
}

if (isset($_POST["run_auto_promotion"])) {
    if ($_FromBatch == "" || $_ToBatch == "") {
        $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Select both From Batch and To Batch for auto promotion.</div>";
    } else {
        $_candSql = "SELECT COUNT(*) AS cnt FROM vw_terminal_promotion_candidates WHERE from_batchid='".mysqli_real_escape_string($con, $_FromBatch)."'";
        $_candRs = mysqli_query($con, $_candSql);
        $_cand = 0;
        if ($_candRs && $_candRow = mysqli_fetch_array($_candRs, MYSQLI_ASSOC)) {
            $_cand = intval($_candRow["cnt"]);
        }
        if ($_cand == 0) {
            $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>No terminal promotion candidates found for selected From Batch.</div>";
        } else {
            $_StartTs = date("Y-m-d H:i:s");
        $stmt = mysqli_prepare($con, "CALL sp_auto_promote_from_terminal(?,?,?,?)");
        if ($stmt) {
            $autoReason = "Auto from terminal report";
            mysqli_stmt_bind_param($stmt, "ssss", $_FromBatch, $_ToBatch, $_RecordedBy, $autoReason);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flushResults($con);
            if ($ok) {
                $_Summary = getPromotionResultSummary($con, $_RecordedBy, $_StartTs);
                $_SESSION['Message'] = "<div style='color:green;padding:8px;border:1px solid #aea;background:#efe;text-align:center;'>Auto promotion completed from terminal suggestions. Promoted: ".$_Summary["promoted"].", Skipped: ".$_Summary["skipped"].".</div>";
            } else {
                $_Error = mysqli_error($con);
                $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Auto promotion failed during execution. $_Error</div>";
            }
        } else {
            $_Error = mysqli_error($con);
            $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Auto promotion procedure not found. $_Error</div>";
        }
        }
    }
}

if (isset($_POST["run_archive_class"])) {
    if ($_FromClass == "" || $_FromBatch == "") {
        $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Select Class and Batch to archive completed class.</div>";
    } else {
        $stmt = mysqli_prepare($con, "CALL sp_archive_completed_class(?,?,?,?)");
        if ($stmt) {
            $archiveRemark = "Archived from promotion center";
            mysqli_stmt_bind_param($stmt, "ssss", $_FromClass, $_FromBatch, $_RecordedBy, $archiveRemark);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flushResults($con);
            if ($ok) {
                $_SESSION['Message'] = "<div style='color:green;padding:8px;border:1px solid #aea;background:#efe;text-align:center;'>Completed class archived successfully. Students moved to alumni archive and removed from active class operations.</div>";
            } else {
                $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Class archive failed during execution.</div>";
            }
        } else {
            $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Archive procedure not found. Update database.sql first.</div>";
        }
    }
}

if (isset($_POST["run_archive_batch"])) {
    if ($_FromBatch == "") {
        $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Select From Batch to archive batch.</div>";
    } else {
        $stmt = mysqli_prepare($con, "CALL sp_archive_batch_for_rollover(?,?,?)");
        if ($stmt) {
            $archiveBatchReason = trim($_Reason);
            if ($archiveBatchReason == "") {
                $archiveBatchReason = "Archived from promotion center";
            }
            mysqli_stmt_bind_param($stmt, "sss", $_FromBatch, $_RecordedBy, $archiveBatchReason);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            flushResults($con);
            if ($ok) {
                $_SESSION['Message'] = "<div style='color:green;padding:8px;border:1px solid #aea;background:#efe;text-align:center;'>Batch archived successfully. Batch status is now inactive and linked active records are closed safely.</div>";
            } else {
                $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Batch archive failed during execution. Ensure latest procedures from database.sql are applied.</div>";
            }
        } else {
            $_SESSION['Message'] = "<div style='color:red;padding:8px;border:1px solid #eaa;background:#fee;text-align:center;'>Batch archive procedure not found. Update database.sql first.</div>";
        }
    }
}

$_SelectedClassCount = 0;
if ($_FromClass != "" && $_FromBatch != "") {
    $_SelectedClassCount = countActiveClassStudents($con, $_FromClass, $_FromBatch);
}

$_SuggestionCount = 0;
if ($_FromBatch != "") {
    $_SQL_SUG_COUNT = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM vw_terminal_promotion_candidates WHERE from_batchid='".mysqli_real_escape_string($con, $_FromBatch)."'");
    if ($_SQL_SUG_COUNT && $_ROW_SUG_COUNT = mysqli_fetch_array($_SQL_SUG_COUNT, MYSQLI_ASSOC)) {
        $_SuggestionCount = intval($_ROW_SUG_COUNT["cnt"]);
    }
}

$_RecentPromotionCount = 0;
$_SQL_LOG_COUNT = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM tblstudentpromotion");
if ($_SQL_LOG_COUNT && $_ROW_LOG_COUNT = mysqli_fetch_array($_SQL_LOG_COUNT, MYSQLI_ASSOC)) {
    $_RecentPromotionCount = intval($_ROW_LOG_COUNT["cnt"]);
}
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/promotion-center.css">
</head>
<body class="promotion-center-page">
<div class="header">
<?php include("menu.php"); ?>
</div>

<div class="main-platform">
    <div class="promotion-center-hero">
        <div class="promotion-center-hero__copy">
            <span class="promotion-center-kicker">Academic Rollover</span>
            <h1>Promotion Center</h1>
            <p>Review class movement, promote students, and archive completed records.</p>
        </div>
        <div class="promotion-center-stats">
            <article class="promotion-center-stat">
                <span>Selected Cohort</span>
                <strong><?php echo $_SelectedClassCount > 0 ? number_format($_SelectedClassCount) : "Not Set"; ?></strong>
                <small><?php echo ($_FromClass != "" && $_FromBatch != "") ? "Active students in the selected class and batch" : "Choose a class and batch first"; ?></small>
            </article>
            <article class="promotion-center-stat">
                <span>Preview Rows</span>
                <strong><?php echo number_format(count($_DryRunRows)); ?></strong>
                <small><?php echo count($_DryRunRows) > 0 ? "Dry run already loaded" : "Run preview before live promotion"; ?></small>
            </article>
            <article class="promotion-center-stat">
                <span>Suggestions</span>
                <strong><?php echo number_format($_SuggestionCount); ?></strong>
                <small><?php echo $_FromBatch != "" ? "Terminal-based suggestions found" : "Select a from batch to load suggestions"; ?></small>
            </article>
            <article class="promotion-center-stat">
                <span>Promotion Logs</span>
                <strong><?php echo number_format($_RecentPromotionCount); ?></strong>
                <small>Recorded promotion history in the system</small>
            </article>
        </div>
    </div>
    <table width="100%">
        <tr>
            <td width="35%" valign="top">
                <div class="form-entry promotion-card promotion-card--control" align="left">
                    <div class="promotion-card__head">
                        <h3>Promotion Setup</h3>
                        <p>Choose the source, destination, and promotion mode before running preview or live actions.</p>
                    </div>
                    <div class="promotion-flash"><?php echo $_SESSION['Message']; ?></div>
                    <form method="post" action="promotion-center.php" class="promotion-form">
                        <div class="promotion-field-block">
                        <label>Promotion Type</label><br/>
                        <select name="promotion_type" id="promotion_type" class="validate[required]">
                            <option value="class" <?php if($_PromotionType!="single"){ echo "selected"; } ?>>Class Promotion (Bulk)</option>
                            <option value="single" <?php if($_PromotionType=="single"){ echo "selected"; } ?>>Single Student</option>
                        </select><br/><br/>
                        </div>

                        <div class="promotion-field-block" id="promotion-student-block">
                        <label>Student (for single)</label><br/>
                        <?php
                        $_SQL_ST = mysqli_query($con, "SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Student' ORDER BY firstname ASC");
                        echo "<select name='userid' id='userid'>";
                        echo "<option value=''>Select Student</option>";
                        while($row = mysqli_fetch_array($_SQL_ST, MYSQLI_ASSOC)){
                            echo "<option value='$row[userid]'>$row[firstname] $row[othernames] $row[surname] ($row[userid])</option>";
                        }
                        echo "</select><br/><br/>";
                        ?>
                        </div>

                        <div class="promotion-field-block" id="promotion-from-class-block">
                        <label>From Class (for class promotion)</label><br/>
                        <?php
                        $_SQL_CL = mysqli_query($con, "SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
                        echo "<select name='from_classid' id='from_classid'>";
                        echo "<option value=''>Select From Class</option>";
                        while($row = mysqli_fetch_array($_SQL_CL, MYSQLI_ASSOC)){
                            echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
                        }
                        echo "</select><br/><br/>";
                        ?>
                        </div>

                        <div class="promotion-field-block">
                        <label>To Class</label><br/>
                        <?php
                        $_SQL_CL2 = mysqli_query($con, "SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
                        echo "<select name='to_classid' id='to_classid' class='validate[required]'>";
                        echo "<option value=''>Select To Class</option>";
                        while($row = mysqli_fetch_array($_SQL_CL2, MYSQLI_ASSOC)){
                            echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
                        }
                        echo "</select><br/><br/>";
                        ?>
                        </div>

                        <div class="promotion-field-block">
                        <label>From Batch</label><br/>
                        <?php
                        $_SQL_B1 = mysqli_query($con, "SELECT batchid,batch,status FROM tblbatch ORDER BY datetimeentry DESC");
                        echo "<select name='from_batchid' id='from_batchid' class='validate[required]'>";
                        echo "<option value=''>Select From Batch</option>";
                        while($row = mysqli_fetch_array($_SQL_B1, MYSQLI_ASSOC)){
                            echo "<option value='$row[batchid]'>$row[batch] ($row[status])</option>";
                        }
                        echo "</select><br/><br/>";
                        ?>
                        </div>

                        <div class="promotion-field-block">
                        <label>To Batch</label><br/>
                        <?php
                        $_SQL_B2 = mysqli_query($con, "SELECT batchid,batch,status FROM tblbatch ORDER BY datetimeentry DESC");
                        echo "<select name='to_batchid' id='to_batchid' class='validate[required]'>";
                        echo "<option value=''>Select To Batch</option>";
                        while($row = mysqli_fetch_array($_SQL_B2, MYSQLI_ASSOC)){
                            echo "<option value='$row[batchid]'>$row[batch] ($row[status])</option>";
                        }
                        echo "</select><br/><br/>";
                        ?>
                        </div>

                        <div class="promotion-field-block">
                        <label>Reason</label><br/>
                        <input type="text" name="reason" id="reason" placeholder="Year-end promotion"/><br/><br/>
                        </div>
                        <div align="center" class="promotion-action-group">
                            <button class="button-save" name="run_promotion" id="run_promotion"><i class="fa fa-level-up"></i> RUN PROMOTION</button>
                            <button class="button-show" name="run_dry_run" id="run_dry_run"><i class="fa fa-search"></i> PREVIEW AUTO PROMOTION</button>
                            <button class="button-show" name="export_dry_run_csv" id="export_dry_run_csv"><i class="fa fa-download"></i> EXPORT PREVIEW CSV</button>
                            <button class="button-show" name="run_auto_promotion" id="run_auto_promotion" onclick="return confirm('Auto promote all suggested students from terminal records?');"><i class="fa fa-magic"></i> AUTO PROMOTE</button>
                        </div>
                        <div class="promotion-archive-block">
                        <hr style="margin:14px 0;"/>
                        <div style="font-size:12px;color:#666;margin-bottom:8px;">Archive Completed Class (e.g., Form 3)</div>
                        <div align="center" class="promotion-action-group promotion-action-group--danger">
                            <button class="button-delete" name="run_archive_class" id="run_archive_class" onclick="return confirm('Archive selected class in selected batch? This moves students to alumni archive and removes them from active class list.');"><i class="fa fa-archive"></i> ARCHIVE COMPLETED CLASS</button>
                        </div>
                        <hr style="margin:14px 0;"/>
                        <div style="font-size:12px;color:#666;margin-bottom:8px;">Archive Batch (set batch inactive and close active records for rollover)</div>
                        <div align="center" class="promotion-action-group promotion-action-group--danger">
                            <button class="button-delete" name="run_archive_batch" id="run_archive_batch" onclick="return confirm('Archive selected FROM BATCH? This sets the batch inactive and closes active class/term/subject/report records while keeping history.');"><i class="fa fa-archive"></i> ARCHIVE BATCH</button>
                        </div>
                        </div>
                    </form>
                </div>
            </td>
            <td width="65%" valign="top">
                <div class="form-entry promotion-card" align="left">
                    <div class="promotion-card__head">
                        <h3>Dry Run Preview</h3>
                        <p>Simulate auto promotion and review each row before changing live data.</p>
                    </div>
                    <?php
                    if (count($_DryRunRows) > 0) {
                        echo "<div class='promotion-table-wrap'><table width='100%' class='promotion-data-table'>";
                        echo "<thead><th>Student</th><th>From Batch</th><th>Current Class</th><th>Suggested Class</th><th>Terminal Note</th><th>Dry Run Status</th></thead><tbody>";
                        foreach ($_DryRunRows as $row) {
                            echo "<tr>";
                            echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
                            echo "<td>$row[from_batch]</td>";
                            echo "<td>$row[class_name]</td>";
                            echo "<td>$row[to_class_name]</td>";
                            echo "<td>$row[note]</td>";
                            if ($row["dry_run_status"] == "READY") {
                                echo "<td style='color:green;font-weight:bold;'>READY</td>";
                            } else {
                                echo "<td style='color:maroon;font-weight:bold;'>$row[dry_run_status]</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</tbody></table></div>";
                    } else {
                        echo "<div class='promotion-empty-state'>Use PREVIEW AUTO PROMOTION to simulate promotion and see what will happen before execution.</div>";
                    }
                    ?>
                </div>
                <div class="form-entry promotion-card" align="left">
                    <div class="promotion-card__head">
                        <h3>Auto Promotion Suggestions</h3>
                        <p>Terminal report suggestions for the selected from batch appear here.</p>
                    </div>
                    <?php
                    $_fromBatchForView = $_FromBatch;
                    if ($_fromBatchForView == "") {
                        echo "<div class='promotion-empty-state'>Select From Batch and run preview to view suggestions for a specific batch.</div>";
                    } else {
                        $_fromBatchForViewSafe = mysqli_real_escape_string($con, $_fromBatchForView);
                        $_SQL_SUG = mysqli_query($con, "SELECT * FROM vw_terminal_promotion_candidates WHERE from_batchid='$_fromBatchForViewSafe' ORDER BY class_name, userid ASC");
                        if($_SQL_SUG){
                            echo "<div class='promotion-table-wrap'><table width='100%' class='promotion-data-table'>";
                            echo "<thead><th>Student</th><th>From Batch</th><th>Current Class</th><th>Suggested Class</th><th>Note</th></thead><tbody>";
                            while($row = mysqli_fetch_array($_SQL_SUG, MYSQLI_ASSOC)){
                                echo "<tr>";
                                echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
                                echo "<td>$row[from_batch]</td>";
                                echo "<td>$row[class_name]</td>";
                                echo "<td>$row[to_class_name]</td>";
                                echo "<td>$row[note]</td>";
                                echo "</tr>";
                            }
                            echo "</tbody></table></div>";
                        } else {
                            echo "<div class='promotion-empty-state'>Suggestion view not found. Run Phase 3 SQL in database.sql.</div>";
                        }
                    }
                    ?>
                </div>
                <div class="form-entry promotion-card" align="left">
                    <div class="promotion-card__head">
                        <h3>Recent Promotions</h3>
                        <p>Review the latest promotion log entries and confirm what happened recently.</p>
                    </div>
                    <?php
                    $_SQL_LOG = mysqli_query($con, "SELECT sp.*,ce1.class_name AS from_class,ce2.class_name AS to_class,b1.batch AS from_batch,b2.batch AS to_batch
                                                    FROM tblstudentpromotion sp
                                                    LEFT JOIN tblclassentry ce1 ON ce1.class_entryid=sp.from_class_entryid
                                                    LEFT JOIN tblclassentry ce2 ON ce2.class_entryid=sp.to_class_entryid
                                                    LEFT JOIN tblbatch b1 ON b1.batchid=sp.from_batchid
                                                    LEFT JOIN tblbatch b2 ON b2.batchid=sp.to_batchid
                                                    ORDER BY sp.promoted_on DESC LIMIT 150");
                    if($_SQL_LOG){
                        echo "<div class='promotion-table-wrap'><table width='100%' class='promotion-data-table'>";
                        echo "<thead><th>Student</th><th>From</th><th>To</th><th>Status</th><th>Reason</th><th>Date/Time</th></thead><tbody>";
                        while($row = mysqli_fetch_array($_SQL_LOG, MYSQLI_ASSOC)){
                            echo "<tr>";
                            echo "<td>$row[userid]</td>";
                            echo "<td>$row[from_class] / $row[from_batch]</td>";
                            echo "<td>$row[to_class] / $row[to_batch]</td>";
                            echo "<td>$row[status]</td>";
                            echo "<td>$row[reason]</td>";
                            echo "<td>$row[promoted_on]</td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table></div>";
                    } else {
                        echo "<div class='promotion-empty-state'>Promotion table not found. Run the Phase 2 SQL in database.sql.</div>";
                    }
                    ?>
                </div>
            </td>
        </tr>
    </table>
</div>
<script>
(function () {
    var state = {
        promotionType: <?php echo json_encode($_PromotionType != "" ? $_PromotionType : "class"); ?>,
        userId: <?php echo json_encode($_UserId); ?>,
        fromClass: <?php echo json_encode($_FromClass); ?>,
        toClass: <?php echo json_encode($_ToClass); ?>,
        fromBatch: <?php echo json_encode($_FromBatch); ?>,
        toBatch: <?php echo json_encode($_ToBatch); ?>,
        reason: <?php echo json_encode($_Reason); ?>
    };

    function setValue(id, value) {
        var field = document.getElementById(id);
        if (field && typeof value !== "undefined" && value !== null) {
            field.value = value;
        }
    }

    function syncPromotionMode() {
        var promotionType = document.getElementById('promotion_type');
        var studentField = document.getElementById('promotion-student-block');
        var fromClassField = document.getElementById('promotion-from-class-block');
        if (!promotionType) {
            return;
        }
        var isSingle = promotionType.value === 'single';
        if (studentField) {
            studentField.style.display = isSingle ? '' : 'none';
        }
        if (fromClassField) {
            fromClassField.style.display = isSingle ? 'none' : '';
        }
    }

    setValue('promotion_type', state.promotionType);
    setValue('userid', state.userId);
    setValue('from_classid', state.fromClass);
    setValue('to_classid', state.toClass);
    setValue('from_batchid', state.fromBatch);
    setValue('to_batchid', state.toBatch);
    setValue('reason', state.reason);
    syncPromotionMode();

    var promotionTypeField = document.getElementById('promotion_type');
    if (promotionTypeField) {
        promotionTypeField.addEventListener('change', syncPromotionMode);
    }
})();
</script>
</body>
</html>

