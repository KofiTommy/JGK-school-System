<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("semester-registry-utils.php");
include_once("report-approval-utils.php");
include_once("company.php");
semester_registry_ensure_academic_year_column($con);
report_approval_ensure_table($con);

if(!report_approval_is_admin_user() && !(function_exists('um_current_user_can_access_module') && um_current_user_can_access_module($con, 'reports'))){
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : 'admin.php'));
    exit();
}

function rab_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function rab_scope_query($filters){
    $pairs = array();
    foreach($filters as $key => $value){
        $value = trim((string)$value);
        if($value === ''){
            continue;
        }
        $pairs[] = rawurlencode((string)$key).'='.rawurlencode($value);
    }
    return implode('&', $pairs);
}

function rab_status_meta($status){
    $status = strtolower(trim((string)$status));
    if($status === 'approved'){
        return array('pill' => 'approval-pill approval-pill--approved', 'label' => 'Approved');
    }
    return array('pill' => 'approval-pill approval-pill--pending', 'label' => 'Pending');
}

$filterBatchId = isset($_REQUEST['batchid']) ? trim((string)$_REQUEST['batchid']) : '';
$filterAcademicYear = isset($_REQUEST['academicyear']) ? trim((string)$_REQUEST['academicyear']) : '';
$filterTermId = isset($_REQUEST['termid']) ? trim((string)$_REQUEST['termid']) : '';
$filterClassId = isset($_REQUEST['classid']) ? trim((string)$_REQUEST['classid']) : '';
$filterStatus = isset($_REQUEST['status']) ? trim((string)$_REQUEST['status']) : '';
$flashType = isset($_GET['flash_type']) ? trim((string)$_GET['flash_type']) : '';
$flashMessage = isset($_GET['flash_message']) ? trim((string)$_GET['flash_message']) : '';

if(isset($_POST['set_report_status'])){
    $scopeBatchId = isset($_POST['scope_batchid']) ? trim((string)$_POST['scope_batchid']) : '';
    $scopeAcademicYear = isset($_POST['scope_academicyear']) ? trim((string)$_POST['scope_academicyear']) : '';
    $scopeTermId = isset($_POST['scope_termid']) ? trim((string)$_POST['scope_termid']) : '';
    $scopeClassId = isset($_POST['scope_classid']) ? trim((string)$_POST['scope_classid']) : '';
    $targetStatus = isset($_POST['target_status']) ? trim((string)$_POST['target_status']) : 'pending';

    $redirectFilters = array(
        'batchid' => isset($_POST['filter_batchid']) ? $_POST['filter_batchid'] : '',
        'academicyear' => isset($_POST['filter_academicyear']) ? $_POST['filter_academicyear'] : '',
        'termid' => isset($_POST['filter_termid']) ? $_POST['filter_termid'] : '',
        'classid' => isset($_POST['filter_classid']) ? $_POST['filter_classid'] : '',
        'status' => isset($_POST['filter_status']) ? $_POST['filter_status'] : ''
    );
    $redirectFilters['flash_type'] = 'warning';
    $redirectFilters['flash_message'] = 'That report scope could not be updated.';

    if(report_approval_scope_requires_release($scopeAcademicYear, $scopeTermId)){
        if(report_approval_set_scope_status($con, $scopeBatchId, $scopeAcademicYear, $scopeTermId, $scopeClassId, $targetStatus, isset($_SESSION['USERID']) ? $_SESSION['USERID'] : '')){
            $redirectFilters['flash_type'] = ($targetStatus === 'approved') ? 'success' : 'info';
            $redirectFilters['flash_message'] = ($targetStatus === 'approved')
                ? 'Class report approved for student viewing.'
                : 'Student access to that class report has been held.';
        }
    }else{
        $redirectFilters['flash_type'] = 'info';
        $redirectFilters['flash_message'] = 'That report scope does not require approval.';
    }

    header("location:report-approval-board.php?".rab_scope_query($redirectFilters));
    exit();
}

$yearSql = semester_registry_resolved_year_sql("tr");
$releaseWhere = "(CAST($yearSql AS UNSIGNED) > 2026 OR (CAST($yearSql AS UNSIGNED) = 2026 AND tr.termname >= 2))";

$batchOptions = array();
$batchRes = mysqli_query($con, "SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");
if($batchRes){
    while($row = mysqli_fetch_array($batchRes, MYSQLI_ASSOC)){
        $batchOptions[] = $row;
    }
}

$yearOptions = array();
$yearQuery = "SELECT DISTINCT $yearSql AS academic_year
    FROM tbltermregistry tr
    WHERE $releaseWhere";
if($filterBatchId !== ''){
    $yearQuery .= " AND tr.batchid='".mysqli_real_escape_string($con, $filterBatchId)."'";
}
$yearQuery .= " ORDER BY CAST(academic_year AS UNSIGNED) DESC";
$yearRes = mysqli_query($con, $yearQuery);
if($yearRes){
    while($row = mysqli_fetch_array($yearRes, MYSQLI_ASSOC)){
        $yearValue = trim((string)$row['academic_year']);
        if($yearValue !== ''){
            $yearOptions[] = $yearValue;
        }
    }
}

$classOptions = array();
$classQuery = "SELECT DISTINCT ce.class_entryid, ce.class_name
    FROM tbltermregistry tr
    INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
    WHERE $releaseWhere";
if($filterBatchId !== ''){
    $classQuery .= " AND tr.batchid='".mysqli_real_escape_string($con, $filterBatchId)."'";
}
if($filterAcademicYear !== ''){
    $classQuery .= " AND $yearSql='".mysqli_real_escape_string($con, $filterAcademicYear)."'";
}
if($filterTermId !== ''){
    $classQuery .= " AND tr.termname='".mysqli_real_escape_string($con, $filterTermId)."'";
}
$classQuery .= " ORDER BY ce.class_name ASC";
$classRes = mysqli_query($con, $classQuery);
if($classRes){
    while($row = mysqli_fetch_array($classRes, MYSQLI_ASSOC)){
        $classOptions[] = $row;
    }
}

$scopeQuery = "SELECT
        tr.batchid,
        $yearSql AS academic_year,
        tr.termname,
        tr.class_entryid AS classid,
        ce.class_name,
        bh.batch,
        COUNT(DISTINCT tr.userid) AS student_total
    FROM tbltermregistry tr
    INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
    LEFT JOIN tblbatch bh ON bh.batchid=tr.batchid
    WHERE $releaseWhere";
if($filterBatchId !== ''){
    $scopeQuery .= " AND tr.batchid='".mysqli_real_escape_string($con, $filterBatchId)."'";
}
if($filterAcademicYear !== ''){
    $scopeQuery .= " AND $yearSql='".mysqli_real_escape_string($con, $filterAcademicYear)."'";
}
if($filterTermId !== ''){
    $scopeQuery .= " AND tr.termname='".mysqli_real_escape_string($con, $filterTermId)."'";
}
if($filterClassId !== ''){
    $scopeQuery .= " AND tr.class_entryid='".mysqli_real_escape_string($con, $filterClassId)."'";
}
$scopeQuery .= " GROUP BY tr.batchid, academic_year, tr.termname, tr.class_entryid, ce.class_name, bh.batch
    ORDER BY CAST(academic_year AS UNSIGNED) DESC, bh.datetimeentry DESC, tr.termname DESC, ce.class_name ASC";

$scopes = array();
$approvedCount = 0;
$pendingCount = 0;
$visibleStudentTotal = 0;
$scopeRes = mysqli_query($con, $scopeQuery);
if($scopeRes){
    while($row = mysqli_fetch_array($scopeRes, MYSQLI_ASSOC)){
        $approvalMeta = report_approval_scope_meta($con, $row['batchid'], $row['academic_year'], $row['termname'], $row['classid']);
        $status = $approvalMeta['allowed'] ? 'approved' : 'pending';
        if($filterStatus !== '' && $filterStatus !== $status){
            continue;
        }
        $row['approval_status'] = $status;
        $row['approval_label'] = $approvalMeta['status_label'];
        $row['approvedby'] = $approvalMeta['approvedby'];
        $row['approveddatetime'] = $approvalMeta['approveddatetime'];
        $row['student_total'] = (int)$row['student_total'];
        $visibleStudentTotal += $row['student_total'];
        if($status === 'approved'){
            $approvedCount++;
        }else{
            $pendingCount++;
        }
        $scopes[] = $row;
    }
}

$totalScopes = count($scopes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/report-approval-board.css">
</head>
<body class="report-approval-board-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="approval-board-shell">
    <section class="approval-board-hero">
        <div>
            <span class="approval-board-kicker">Examination Control</span>
            <h1>Report Approval Board</h1>
            <p>Approve class terminal reports for student viewing from <strong>2026 Semester 2</strong> onward. Students stay blocked until you release the scope.</p>
        </div>
        <div class="approval-board-summary-grid">
            <article class="approval-summary-card">
                <span>Visible Scopes</span>
                <strong><?php echo number_format($totalScopes); ?></strong>
            </article>
            <article class="approval-summary-card approval-summary-card--success">
                <span>Approved</span>
                <strong><?php echo number_format($approvedCount); ?></strong>
            </article>
            <article class="approval-summary-card approval-summary-card--warning">
                <span>Pending</span>
                <strong><?php echo number_format($pendingCount); ?></strong>
            </article>
            <article class="approval-summary-card">
                <span>Students Covered</span>
                <strong><?php echo number_format($visibleStudentTotal); ?></strong>
            </article>
        </div>
    </section>

    <?php if($flashMessage !== ''){ ?>
    <div class="approval-flash approval-flash--<?php echo rab_esc($flashType !== '' ? $flashType : 'info'); ?>">
        <?php echo rab_esc($flashMessage); ?>
    </div>
    <?php } ?>

    <section class="approval-board-panel">
        <div class="approval-board-panel__header">
            <div>
                <span class="approval-board-eyebrow">Filter Board</span>
                <h2>Choose the report scope you want to manage</h2>
            </div>
            <a class="approval-reset-link" href="report-approval-board.php"><i class="fa fa-undo"></i> Reset</a>
        </div>

        <form method="get" action="report-approval-board.php" class="approval-filter-grid">
            <label>
                <span>Batch</span>
                <select name="batchid">
                    <option value="">All batches</option>
                    <?php foreach($batchOptions as $option){ ?>
                    <option value="<?php echo rab_esc($option['batchid']); ?>" <?php echo ($filterBatchId === (string)$option['batchid']) ? 'selected' : ''; ?>><?php echo rab_esc($option['batch']); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span>Academic Year</span>
                <select name="academicyear">
                    <option value="">All years</option>
                    <?php foreach($yearOptions as $option){ ?>
                    <option value="<?php echo rab_esc($option); ?>" <?php echo ($filterAcademicYear === (string)$option) ? 'selected' : ''; ?>><?php echo rab_esc($option); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span>Semester</span>
                <select name="termid">
                    <option value="">All semesters</option>
                    <option value="1" <?php echo ($filterTermId === '1') ? 'selected' : ''; ?>>Semester 1</option>
                    <option value="2" <?php echo ($filterTermId === '2') ? 'selected' : ''; ?>>Semester 2</option>
                    <option value="3" <?php echo ($filterTermId === '3') ? 'selected' : ''; ?>>Semester 3</option>
                </select>
            </label>
            <label>
                <span>Class</span>
                <select name="classid">
                    <option value="">All classes</option>
                    <?php foreach($classOptions as $option){ ?>
                    <option value="<?php echo rab_esc($option['class_entryid']); ?>" <?php echo ($filterClassId === (string)$option['class_entryid']) ? 'selected' : ''; ?>><?php echo rab_esc($option['class_name']); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="pending" <?php echo ($filterStatus === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo ($filterStatus === 'approved') ? 'selected' : ''; ?>>Approved</option>
                </select>
            </label>
            <div class="approval-filter-actions">
                <button type="submit" class="approval-primary-btn"><i class="fa fa-search"></i> Apply Filter</button>
            </div>
        </form>
    </section>

    <section class="approval-board-grid">
        <?php if($totalScopes > 0){ ?>
            <?php foreach($scopes as $scope){ $statusMeta = rab_status_meta($scope['approval_status']); ?>
            <article class="approval-card">
                <div class="approval-card__meta">
                    <span class="<?php echo rab_esc($statusMeta['pill']); ?>"><?php echo rab_esc($statusMeta['label']); ?></span>
                    <span class="approval-card__students"><?php echo number_format((int)$scope['student_total']); ?> student(s)</span>
                </div>
                <h3><?php echo rab_esc($scope['class_name']); ?></h3>
                <p><?php echo rab_esc(trim((string)$scope['batch']) !== '' ? $scope['batch'] : 'No batch label'); ?></p>
                <dl class="approval-card__facts">
                    <div><dt>Academic Year</dt><dd><?php echo rab_esc($scope['academic_year']); ?></dd></div>
                    <div><dt>Semester</dt><dd><?php echo rab_esc('Semester '.$scope['termname']); ?></dd></div>
                    <div><dt>Portal Status</dt><dd><?php echo rab_esc($scope['approval_label']); ?></dd></div>
                    <div><dt>Approved By</dt><dd><?php echo rab_esc($scope['approvedby'] !== '' ? $scope['approvedby'] : 'Not yet approved'); ?></dd></div>
                </dl>
                <?php if(trim((string)$scope['approveddatetime']) !== ''){ ?>
                <div class="approval-card__stamp">Released on <?php echo rab_esc(date("d M Y, H:i", strtotime((string)$scope['approveddatetime']))); ?></div>
                <?php } ?>
                <form method="post" action="report-approval-board.php" class="approval-card__actions">
                    <input type="hidden" name="scope_batchid" value="<?php echo rab_esc($scope['batchid']); ?>">
                    <input type="hidden" name="scope_academicyear" value="<?php echo rab_esc($scope['academic_year']); ?>">
                    <input type="hidden" name="scope_termid" value="<?php echo rab_esc($scope['termname']); ?>">
                    <input type="hidden" name="scope_classid" value="<?php echo rab_esc($scope['classid']); ?>">
                    <input type="hidden" name="target_status" value="approved">

                    <input type="hidden" name="filter_batchid" value="<?php echo rab_esc($filterBatchId); ?>">
                    <input type="hidden" name="filter_academicyear" value="<?php echo rab_esc($filterAcademicYear); ?>">
                    <input type="hidden" name="filter_termid" value="<?php echo rab_esc($filterTermId); ?>">
                    <input type="hidden" name="filter_classid" value="<?php echo rab_esc($filterClassId); ?>">
                    <input type="hidden" name="filter_status" value="<?php echo rab_esc($filterStatus); ?>">

                    <button type="submit" name="set_report_status" value="1" class="approval-primary-btn">
                        <i class="fa fa-check"></i> Approve Student View
                    </button>
                </form>
                <form method="post" action="report-approval-board.php" class="approval-card__actions approval-card__actions--secondary">
                    <input type="hidden" name="scope_batchid" value="<?php echo rab_esc($scope['batchid']); ?>">
                    <input type="hidden" name="scope_academicyear" value="<?php echo rab_esc($scope['academic_year']); ?>">
                    <input type="hidden" name="scope_termid" value="<?php echo rab_esc($scope['termname']); ?>">
                    <input type="hidden" name="scope_classid" value="<?php echo rab_esc($scope['classid']); ?>">
                    <input type="hidden" name="target_status" value="pending">

                    <input type="hidden" name="filter_batchid" value="<?php echo rab_esc($filterBatchId); ?>">
                    <input type="hidden" name="filter_academicyear" value="<?php echo rab_esc($filterAcademicYear); ?>">
                    <input type="hidden" name="filter_termid" value="<?php echo rab_esc($filterTermId); ?>">
                    <input type="hidden" name="filter_classid" value="<?php echo rab_esc($filterClassId); ?>">
                    <input type="hidden" name="filter_status" value="<?php echo rab_esc($filterStatus); ?>">

                    <button type="submit" name="set_report_status" value="1" class="approval-secondary-btn">
                        <i class="fa fa-pause"></i> Hold Student View
                    </button>
                </form>
            </article>
            <?php } ?>
        <?php }else{ ?>
            <div class="approval-empty-state">
                <h3>No report scopes matched this filter.</h3>
                <p>Try another batch, class, or academic year. This board only shows semesters that require approval from `2026 Semester 2` onward.</p>
            </div>
        <?php } ?>
    </section>
</main>
</body>
</html>
