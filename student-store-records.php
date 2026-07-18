<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("storekeeper-utils.php");
include("house-master-utils.php");
ensure_storekeeper_tables($con);

$_IsStudentViewer = isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
    $_SESSION['ACCESSLEVEL'] === 'user' &&
    $_SESSION['SYSTEMTYPE'] === 'Student';
$_CanManageStores = storekeeper_can_manage_module($con, 'stores_management');

if (!$_IsStudentViewer && !$_CanManageStores) {
    header("location:" . storekeeper_landing_page());
    exit();
}

if (!function_exists('ssr_date')) {
function ssr_date($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y", $timestamp) : $value;
}
}

$_TargetStudentId = $_IsStudentViewer
    ? (isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '')
    : trim((string)(isset($_GET['studentid']) ? $_GET['studentid'] : ''));

if ($_TargetStudentId === '') {
    header("location:" . ($_IsStudentViewer ? 'student-page.php' : 'store-student-issue.php'));
    exit();
}

$_StudentRow = storekeeper_get_student_row($con, $_TargetStudentId);
if (!$_StudentRow) {
    header("location:" . ($_IsStudentViewer ? 'student-page.php' : 'store-student-issue.php'));
    exit();
}

$_Rows = storekeeper_fetch_student_issue_rows($con, array(
    'studentid' => $_TargetStudentId,
    'limit' => 500
));

$_Outstanding = 0;
$_Returned = 0;
$_Overdue = 0;
foreach ($_Rows as $_Row) {
    $_DisplayStatus = storekeeper_student_issue_status($_Row);
    if (in_array($_DisplayStatus, array('issued', 'awaiting_return', 'overdue'), true)) {
        $_Outstanding++;
    }
    if ($_DisplayStatus === 'returned') {
        $_Returned++;
    }
    if ($_DisplayStatus === 'overdue') {
        $_Overdue++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/storekeeper.css">
</head>
<body class="storekeeper-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<main class="sk-main">
    <div class="sk-shell">
        <section class="sk-hero">
            <div>
                <span class="sk-kicker"><i class="fa fa-user"></i> <?php echo $_IsStudentViewer ? 'My Store Items' : 'Student Store Record'; ?></span>
                <h1><?php echo $_IsStudentViewer ? 'Items I received from the school store.' : 'Store items collected by this student.'; ?></h1>
                <p><?php echo $_IsStudentViewer ? 'This page keeps your personal history of books, items, and other materials collected from the store, including any expected return dates.' : 'Use this page to review one student’s store history, expected returns, and return status.'; ?></p>
                <div class="sk-hero__chips">
                    <span class="sk-chip"><i class="fa fa-id-card-o"></i> Student ID: <?php echo storekeeper_esc($_StudentRow['userid']); ?></span>
                    <span class="sk-chip"><i class="fa fa-user-circle-o"></i> <?php echo storekeeper_esc(storekeeper_student_display_name($_StudentRow)); ?></span>
                    <?php if (trim((string)$_StudentRow['_population_label']) !== '') { ?>
                    <span class="sk-chip"><i class="fa fa-users"></i> <?php echo storekeeper_esc($_StudentRow['_population_label']); ?></span>
                    <?php } ?>
                </div>
                <?php if (!$_IsStudentViewer) { ?>
                <div class="sk-link-grid" style="margin-top:16px;">
                    <a class="sk-link-chip" href="store-student-issue.php"><i class="fa fa-arrow-left"></i> Back To Student Issue Register</a>
                </div>
                <?php } ?>
            </div>
            <div class="sk-stats">
                <article class="sk-stat">
                    <span>Total Records</span>
                    <strong><?php echo number_format(count($_Rows)); ?></strong>
                    <small>All store records linked to this student.</small>
                </article>
                <article class="sk-stat">
                    <span>Outstanding</span>
                    <strong><?php echo number_format((int)$_Outstanding); ?></strong>
                    <small>Items still recorded as out with the student.</small>
                </article>
                <article class="sk-stat">
                    <span>Overdue</span>
                    <strong><?php echo number_format((int)$_Overdue); ?></strong>
                    <small>Returnable items already past the expected return date.</small>
                </article>
                <article class="sk-stat">
                    <span>Returned</span>
                    <strong><?php echo number_format((int)$_Returned); ?></strong>
                    <small>Items already returned and closed in the register.</small>
                </article>
            </div>
        </section>

        <section class="sk-panel">
            <div class="sk-panel__header">
                <div>
                    <h2><?php echo $_IsStudentViewer ? 'My Store History' : 'Student Store History'; ?></h2>
                    <p><?php echo $_IsStudentViewer ? 'Keep this page as your reference for what you collected and what is expected back from you.' : 'This is the full store ledger for the selected student.'; ?></p>
                </div>
            </div>
            <div class="sk-panel__body">
                <?php if (empty($_Rows)) { ?>
                <div class="sk-empty">No store record has been saved for this student yet.</div>
                <?php } else { ?>
                <div class="sk-table-wrap">
                    <table class="sk-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Collected Date</th>
                                <th>Expected Return</th>
                                <th>Actual Return</th>
                                <th>Purpose / Notes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_Rows as $_Row) { ?>
                            <tr>
                                <td>
                                    <?php echo storekeeper_esc($_Row['itemname']); ?>
                                    <small><?php echo storekeeper_esc($_Row['unitname']); ?></small>
                                </td>
                                <td><?php echo storekeeper_format_quantity($_Row['quantity']); ?></td>
                                <td>
                                    <?php echo ssr_date($_Row['issuedate']); ?>
                                    <?php if (trim((string)$_Row['issuecondition']) !== '') { ?>
                                    <small>Condition: <?php echo storekeeper_esc($_Row['issuecondition']); ?></small>
                                    <?php } ?>
                                </td>
                                <td><?php echo ssr_date($_Row['expectedreturndate']); ?></td>
                                <td>
                                    <?php echo ssr_date($_Row['actualreturndate']); ?>
                                    <?php if (trim((string)$_Row['returncondition']) !== '') { ?>
                                    <small>Condition: <?php echo storekeeper_esc($_Row['returncondition']); ?></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php echo storekeeper_esc($_Row['purpose']); ?>
                                    <?php if (trim((string)$_Row['notes']) !== '') { ?>
                                    <small><?php echo storekeeper_esc($_Row['notes']); ?></small>
                                    <?php } ?>
                                </td>
                                <td><?php echo storekeeper_student_issue_status_badge_html($_Row); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </section>
    </div>
</main>
</body>
</html>
