<?php
session_start();
$_SESSION['Message'] = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";
include("check-login.php");
include("dbstring.php");
include("teacher-billing-utils.php");
ensure_teacher_billing_table($con);
ensure_teacher_billing_item_table($con);

if (!teacher_billing_can_manage_assignments()) {
    header("location:" . teacher_billing_landing_page());
    exit();
}

if (!function_exists('tba_redirect')) {
    function tba_redirect($query = '')
    {
        header("location:teacher-billing-assignment.php" . $query);
        exit();
    }
}

if (!function_exists('tba_esc')) {
    function tba_esc($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tba_flash_html')) {
    function tba_flash_html($tone, $message)
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

        return "<div class='tba-flash tba-flash--" . $tone . "'><span class='tba-flash__icon'><i class='fa " . $icon . "'></i></span><div class='tba-flash__body'>" . $message . "</div></div>";
    }
}

if (!function_exists('tba_status_badge_html')) {
    function tba_status_badge_html($status)
    {
        $status = strtolower(trim((string)$status));
        $tone = ($status === 'active') ? 'active' : 'inactive';
        $label = ($status === 'active') ? 'Active' : 'Inactive';
        return "<span class='tba-badge tba-badge--" . $tone . "'>" . tba_esc($label) . "</span>";
    }
}

if (isset($_POST['save_teacher_billing_assignment'])) {
    $_TeacherId = isset($_POST['userid']) ? trim((string)$_POST['userid']) : "";
    $_ClassId = isset($_POST['classid']) ? trim((string)$_POST['classid']) : "";
    $_BatchId = isset($_POST['batchid']) ? trim((string)$_POST['batchid']) : "";
    $_TermName = isset($_POST['termname']) ? (int)$_POST['termname'] : 0;
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";

    if ($_TeacherId === '' || $_ClassId === '' || $_BatchId === '' || $_TermName <= 0) {
        $_SESSION['Message'] = tba_flash_html('error', 'Please select teacher, class, batch, and semester.');
        tba_redirect();
    }

    $_TeacherIdEsc = mysqli_real_escape_string($con, $_TeacherId);
    $_ClassIdEsc = mysqli_real_escape_string($con, $_ClassId);
    $_BatchIdEsc = mysqli_real_escape_string($con, $_BatchId);
    $_RecordedByEsc = mysqli_real_escape_string($con, $_RecordedBy);

    $_SQL_EXIST = mysqli_query($con, "SELECT assignmentid
        FROM tblteacherbillingassignment
        WHERE userid='$_TeacherIdEsc'
          AND classid='$_ClassIdEsc'
          AND batchid='$_BatchIdEsc'
          AND termname='$_TermName'
        LIMIT 1");

    if ($_SQL_EXIST && ($row_exist = mysqli_fetch_array($_SQL_EXIST, MYSQLI_ASSOC))) {
        $_AssignmentIdEsc = mysqli_real_escape_string($con, $row_exist['assignmentid']);
        $_SQL_UPDATE = mysqli_query($con, "UPDATE tblteacherbillingassignment
            SET status='active', datetimeentry=NOW(), recordedby='$_RecordedByEsc'
            WHERE assignmentid='$_AssignmentIdEsc'");
        if ($_SQL_UPDATE) {
            $_SESSION['Message'] = tba_flash_html('success', 'Teacher billing assignment updated successfully.');
        } else {
            $_SESSION['Message'] = tba_flash_html('error', 'Failed to update assignment: ' . tba_esc(mysqli_error($con)));
        }
        tba_redirect();
    }

    include("code.php");
    $_AssignmentIdEsc = mysqli_real_escape_string($con, trim((string)$code));
    $_SQL_INSERT = mysqli_query($con, "INSERT INTO tblteacherbillingassignment(assignmentid,userid,classid,batchid,termname,status,datetimeentry,recordedby)
        VALUES('$_AssignmentIdEsc','$_TeacherIdEsc','$_ClassIdEsc','$_BatchIdEsc','$_TermName','active',NOW(),'$_RecordedByEsc')");
    if ($_SQL_INSERT) {
        $_SESSION['Message'] = tba_flash_html('success', 'Teacher billing assignment saved successfully.');
    } else {
        $_SESSION['Message'] = tba_flash_html('error', 'Failed to save assignment: ' . tba_esc(mysqli_error($con)));
    }
    tba_redirect();
}

if (isset($_GET['deactivate_assignment'])) {
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)$_GET['deactivate_assignment']));
    if ($_AssignmentId !== '') {
        $_SQL_D = mysqli_query($con, "UPDATE tblteacherbillingassignment SET status='inactive' WHERE assignmentid='$_AssignmentId'");
        $_SESSION['Message'] = $_SQL_D
            ? tba_flash_html('warning', 'Assignment deactivated.')
            : tba_flash_html('error', 'Failed to deactivate assignment: ' . tba_esc(mysqli_error($con)));
    }
    tba_redirect();
}

if (isset($_GET['delete_assignment'])) {
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)$_GET['delete_assignment']));
    if ($_AssignmentId !== '') {
        @mysqli_query($con, "DELETE FROM tblteacherbillingassignmentitem WHERE assignmentid='$_AssignmentId'");
        $_SQL_DEL = mysqli_query($con, "DELETE FROM tblteacherbillingassignment WHERE assignmentid='$_AssignmentId'");
        $_SESSION['Message'] = $_SQL_DEL
            ? tba_flash_html('warning', 'Assignment deleted.')
            : tba_flash_html('error', 'Failed to delete assignment: ' . tba_esc(mysqli_error($con)));
    }
    tba_redirect();
}

if (isset($_POST['save_assignment_items'])) {
    $_AssignmentId = isset($_POST['assignmentid']) ? trim((string)$_POST['assignmentid']) : "";
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
    $_AssignmentRow = teacher_billing_assignment_row($con, $_AssignmentId);
    if (!$_AssignmentRow) {
        $_SESSION['Message'] = tba_flash_html('error', 'The selected billing assignment could not be found.');
        tba_redirect();
    }

    $_AllowedRows = teacher_billing_scope_itemprice_rows($con, $_AssignmentRow['classid'], $_AssignmentRow['batchid'], $_AssignmentRow['termname']);
    $_AllowedMap = array();
    foreach ($_AllowedRows as $_allowedRow) {
        $_AllowedMap[(string)$_allowedRow['itempriceid']] = true;
    }

    $_SubmittedItems = isset($_POST['itempriceids']) && is_array($_POST['itempriceids']) ? $_POST['itempriceids'] : array();
    $_ValidItems = array();
    foreach ($_SubmittedItems as $_ItemPriceId) {
        $_ItemPriceId = trim((string)$_ItemPriceId);
        if ($_ItemPriceId !== '' && isset($_AllowedMap[$_ItemPriceId])) {
            $_ValidItems[] = $_ItemPriceId;
        }
    }

    $_Saved = teacher_billing_assignment_replace_items($con, $_AssignmentId, $_ValidItems, $_RecordedBy);
    if ($_Saved) {
        if (count($_ValidItems) > 0) {
            $_SESSION['Message'] = tba_flash_html('success', 'Teacher billing items updated successfully.');
        } else {
            $_SESSION['Message'] = tba_flash_html('info', 'No specific items were selected. The teacher will be able to collect all billed items in that assigned scope.');
        }
    } else {
        $_SESSION['Message'] = tba_flash_html('error', 'Some billing items could not be saved. Please try again.');
    }
    header("location:teacher-billing-assignment.php?set_items=" . urlencode($_AssignmentId));
    exit();
}

$_TeacherOptions = array();
$_SQL_T = mysqli_query($con, "SELECT userid,firstname,surname,othernames
    FROM tblsystemuser
    WHERE systemtype='Teacher' AND status='active'
    ORDER BY firstname ASC, surname ASC");
if ($_SQL_T) {
    while ($_row_t = mysqli_fetch_array($_SQL_T, MYSQLI_ASSOC)) {
        $_TeacherOptions[] = $_row_t;
    }
}

$_ClassOptions = teacher_billing_class_options($con);
$_BatchOptions = teacher_billing_batch_options($con);

$_ManageAssignmentId = isset($_GET['set_items']) ? trim((string)$_GET['set_items']) : "";
$_ManageAssignmentRow = $_ManageAssignmentId !== "" ? teacher_billing_assignment_row($con, $_ManageAssignmentId) : null;
$_ManageItemRows = array();
$_SelectedItems = array();
if ($_ManageAssignmentRow) {
    $_ManageItemRows = teacher_billing_scope_itemprice_rows($con, $_ManageAssignmentRow['classid'], $_ManageAssignmentRow['batchid'], $_ManageAssignmentRow['termname']);
    foreach (teacher_billing_assignment_item_rows($con, $_ManageAssignmentId) as $_SelectedRow) {
        $_SelectedItems[(string)$_SelectedRow['itempriceid']] = true;
    }
}

$_AssignmentRows = array();
$_ActiveAssignments = 0;
$_InactiveAssignments = 0;
$_DistinctTeacherMap = array();
$_SQL_A = mysqli_query($con, "SELECT
        tba.*,
        su.firstname,
        su.surname,
        su.othernames,
        ce.class_name,
        bh.batch,
        (
            SELECT COUNT(*)
            FROM tblteacherbillingassignmentitem tbai
            WHERE tbai.assignmentid=tba.assignmentid
        ) AS selected_item_count
    FROM tblteacherbillingassignment tba
    INNER JOIN tblsystemuser su ON su.userid=tba.userid
    INNER JOIN tblclassentry ce ON ce.class_entryid=tba.classid
    INNER JOIN tblbatch bh ON bh.batchid=tba.batchid
    ORDER BY tba.datetimeentry DESC");
if ($_SQL_A) {
    while ($_row_a = mysqli_fetch_array($_SQL_A, MYSQLI_ASSOC)) {
        $_AssignmentRows[] = $_row_a;
        $_DistinctTeacherMap[(string)$_row_a['userid']] = true;
        if ((string)$_row_a['status'] === 'active') {
            $_ActiveAssignments++;
        } else {
            $_InactiveAssignments++;
        }
    }
}

$_MessageHtml = $_SESSION['Message'];
$_SESSION['Message'] = "";
$_TotalAssignments = count($_AssignmentRows);
$_AssignedTeachers = count($_DistinctTeacherMap);
$_SelectableItemCount = count($_ManageItemRows);
$_SelectedItemCount = count($_SelectedItems);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/teacher-billing-assignment.css">
<script src="scripts/teacher-billing-assignment.js" defer></script>
</head>
<body class="teacher-billing-assignment-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <main class="tba-shell">
        <section class="tba-hero">
            <div class="tba-hero__copy">
                <span class="tba-kicker"><i class="fa fa-credit-card"></i> Teacher Billing Assignment</span>
                <h1>Assign Billing Responsibilities</h1>
                <p>Assign a teacher to a class, batch, and semester, then choose the billed items the teacher is allowed to collect.</p>
                <div class="tba-hero__chips">
                    <span class="tba-chip"><i class="fa fa-users"></i> Active Teachers: <?php echo number_format((int)count($_TeacherOptions)); ?></span>
                    <span class="tba-chip"><i class="fa fa-building"></i> Classes: <?php echo number_format((int)count($_ClassOptions)); ?></span>
                    <span class="tba-chip"><i class="fa fa-calendar"></i> Batches: <?php echo number_format((int)count($_BatchOptions)); ?></span>
                </div>
            </div>
            <div class="tba-stats">
                <article class="tba-stat">
                    <span>Total Assignments</span>
                    <strong><?php echo number_format((int)$_TotalAssignments); ?></strong>
                    <small>Every saved teacher billing scope in the system.</small>
                </article>
                <article class="tba-stat">
                    <span>Active Assignments</span>
                    <strong><?php echo number_format((int)$_ActiveAssignments); ?></strong>
                    <small>Scopes currently available for teacher billing activity.</small>
                </article>
                <article class="tba-stat">
                    <span>Inactive Assignments</span>
                    <strong><?php echo number_format((int)$_InactiveAssignments); ?></strong>
                    <small>Saved scopes that were deactivated for now.</small>
                </article>
                <article class="tba-stat tba-stat--accent">
                    <span>Assigned Teachers</span>
                    <strong><?php echo number_format((int)$_AssignedTeachers); ?></strong>
                    <small>Unique teachers with at least one billing assignment.</small>
                </article>
            </div>
        </section>

        <div class="tba-layout">
            <aside class="tba-sidebar">
                <section class="tba-surface tba-form-surface">
                    <div class="tba-panel-head">
                        <div>
                            <span class="tba-panel-kicker">Assignment Setup</span>
                            <h2>Create or Update a Billing Assignment</h2>
                            <p>Select the teacher, class, batch, and semester below. If the same assignment already exists, saving will reactivate it instead of creating a duplicate.</p>
                        </div>
                    </div>

                    <?php if ($_MessageHtml !== "") { ?>
                    <div class="tba-message-stack"><?php echo $_MessageHtml; ?></div>
                    <?php } ?>

                    <form method="post" action="teacher-billing-assignment.php" class="tba-form">
                        <div class="tba-form-grid">
                            <label class="tba-field">
                                <span>Teacher</span>
                                <select id="userid" name="userid" class="validate[required]">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($_TeacherOptions as $_teacherRow) {
                                        $_TeacherName = trim((string)$_teacherRow['firstname'] . " " . (string)$_teacherRow['othernames'] . " " . (string)$_teacherRow['surname']);
                                    ?>
                                    <option value="<?php echo tba_esc($_teacherRow['userid']); ?>"><?php echo tba_esc($_TeacherName); ?> (<?php echo tba_esc($_teacherRow['userid']); ?>)</option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="tba-field">
                                <span>Class</span>
                                <select id="classid" name="classid" class="validate[required]">
                                    <option value="">Select Class</option>
                                    <?php foreach ($_ClassOptions as $_classRow) { ?>
                                    <option value="<?php echo tba_esc($_classRow['class_entryid']); ?>"><?php echo tba_esc($_classRow['class_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="tba-field">
                                <span>Batch</span>
                                <select id="batchid" name="batchid" class="validate[required]">
                                    <option value="">Select Batch</option>
                                    <?php foreach ($_BatchOptions as $_batchRow) { ?>
                                    <option value="<?php echo tba_esc($_batchRow['batchid']); ?>"><?php echo tba_esc($_batchRow['batch']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="tba-field">
                                <span>Semester</span>
                                <select id="termname" name="termname" class="validate[required]">
                                    <option value="">Select Semester</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                </select>
                            </label>
                        </div>

                        <div class="tba-inline-note">
                            <i class="fa fa-info-circle"></i>
                            <span>Use this form when a teacher needs permission to collect billing items for a specific class, batch, and semester.</span>
                        </div>

                        <div class="tba-actions">
                            <button class="tba-btn tba-btn--primary" id="save_teacher_billing_assignment" name="save_teacher_billing_assignment" type="submit"><i class="fa fa-save"></i> Save Assignment</button>
                        </div>
                    </form>
                </section>
            </aside>

            <section class="tba-main">
                <?php if ($_ManageAssignmentRow) {
                    $_ManageTeacherName = trim((string)$_ManageAssignmentRow['firstname'] . " " . (string)$_ManageAssignmentRow['othernames'] . " " . (string)$_ManageAssignmentRow['surname']);
                ?>
                <section class="tba-surface print-hide">
                    <div class="tba-panel-head">
                        <div>
                            <span class="tba-panel-kicker">Billing Items</span>
                            <h2>Select Billing Items</h2>
                            <p>Leave all items unchecked if the teacher should be allowed to collect every active billed item in this class, batch, and semester.</p>
                        </div>
                        <a class="tba-btn tba-btn--secondary" href="teacher-billing-assignment.php"><i class="fa fa-times"></i> Close</a>
                    </div>

                    <div class="tba-meta-grid">
                        <div class="tba-meta-card">
                            <span>Teacher</span>
                            <strong><?php echo tba_esc($_ManageTeacherName); ?></strong>
                            <small><?php echo tba_esc($_ManageAssignmentRow['userid']); ?></small>
                        </div>
                        <div class="tba-meta-card">
                            <span>Class</span>
                            <strong><?php echo tba_esc($_ManageAssignmentRow['class_name']); ?></strong>
                            <small>Semester <?php echo tba_esc($_ManageAssignmentRow['termname']); ?></small>
                        </div>
                        <div class="tba-meta-card">
                            <span>Batch</span>
                            <strong><?php echo tba_esc($_ManageAssignmentRow['batch']); ?></strong>
                            <small><?php echo $_SelectedItemCount > 0 ? number_format((int)$_SelectedItemCount) . ' item(s) selected' : 'All items currently allowed'; ?></small>
                        </div>
                    </div>

                    <?php if (empty($_ManageItemRows)) { ?>
                    <div class="tba-inline-note tba-inline-note--warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <span>No active class billing items exist yet for this class, batch, and semester. Create them first in <a href="class-billing.php">Billing Manager</a>.</span>
                    </div>
                    <?php } else { ?>
                    <form method="post" action="teacher-billing-assignment.php?set_items=<?php echo urlencode($_ManageAssignmentId); ?>" class="tba-item-form">
                        <input type="hidden" name="assignmentid" value="<?php echo tba_esc($_ManageAssignmentId); ?>">

                        <div class="tba-toolbar tba-toolbar--items">
                            <div class="tba-toolbar__copy">
                                <strong><?php echo number_format((int)$_SelectableItemCount); ?> available item<?php echo $_SelectableItemCount === 1 ? '' : 's'; ?></strong>
                                <span><?php echo $_SelectedItemCount > 0 ? number_format((int)$_SelectedItemCount) . ' selected for this teacher.' : 'Nothing selected means the teacher gets access to all items in this scope.'; ?></span>
                            </div>
                            <label class="tba-search">
                                <i class="fa fa-search"></i>
                                <input type="search" placeholder="Search billing items" data-item-search>
                            </label>
                        </div>

                        <div class="tba-item-grid" data-item-grid>
                            <?php foreach ($_ManageItemRows as $_ItemRow) {
                                $_ItemPriceId = (string)$_ItemRow['itempriceid'];
                                $_ItemSearch = strtolower(trim((string)$_ItemRow['itemname'] . " " . (string)$_ItemPriceId));
                            ?>
                            <label class="tba-item-card" data-item-card data-search="<?php echo tba_esc($_ItemSearch); ?>">
                                <span class="tba-item-card__check">
                                    <input type="checkbox" name="itempriceids[]" value="<?php echo tba_esc($_ItemPriceId); ?>"<?php echo isset($_SelectedItems[$_ItemPriceId]) ? " checked" : ""; ?>>
                                </span>
                                <span class="tba-item-card__body">
                                    <strong><?php echo tba_esc($_ItemRow['itemname']); ?></strong>
                                    <small>Item Price ID: <?php echo tba_esc($_ItemPriceId); ?></small>
                                </span>
                                <span class="tba-item-card__price"><?php echo tba_esc((string)$_SESSION['SYMBOL']); ?> <?php echo number_format((float)$_ItemRow['price'], 2); ?></span>
                            </label>
                            <?php } ?>
                        </div>

                        <div class="tba-empty-state tba-empty-state--inline" data-item-empty hidden>
                            <h3>No billing items match this search</h3>
                            <p>Try a shorter item name or clear the search to see the full list again.</p>
                        </div>

                        <div class="tba-actions">
                            <button class="tba-btn tba-btn--primary" type="submit" name="save_assignment_items"><i class="fa fa-save"></i> Save Billing Items</button>
                            <a class="tba-btn tba-btn--secondary" href="teacher-billing-assignment.php"><i class="fa fa-arrow-left"></i> Back To Assignments</a>
                        </div>
                    </form>
                    <?php } ?>
                </section>
                <?php } ?>

                <section class="tba-surface">
                    <div class="tba-panel-head">
                        <div>
                            <span class="tba-panel-kicker">Assignment List</span>
                            <h2>Teacher Billing Assignments</h2>
                            <p>Review each teacher's class billing assignment, selected items, and available actions from one list.</p>
                        </div>
                        <span class="tba-panel-tag"><i class="fa fa-list"></i> <?php echo number_format((int)$_TotalAssignments); ?> assignment<?php echo $_TotalAssignments === 1 ? '' : 's'; ?></span>
                    </div>

                    <div class="tba-toolbar print-hide">
                        <label class="tba-search">
                            <i class="fa fa-search"></i>
                            <input type="search" placeholder="Search teacher, class, batch, or semester" data-assignment-search>
                        </label>
                        <button class="tba-btn tba-btn--secondary" type="button" onclick="window.print()"><i class="fa fa-print"></i> Print Assignments</button>
                    </div>

                    <?php if (empty($_AssignmentRows)) { ?>
                    <div class="tba-empty-state">
                        <h3>No billing assignments yet</h3>
                        <p>Start by creating a teacher billing scope from the setup panel on the left.</p>
                    </div>
                    <?php } else { ?>
                    <div class="tba-table-wrap">
                        <table class="tba-table">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>Teacher</th>
                                    <th>Class</th>
                                    <th>Semester</th>
                                    <th>Batch</th>
                                    <th>Billing Items</th>
                                    <th>Status</th>
                                    <th>Date / Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_AssignmentRows as $_row_a) {
                                    $_TeacherName = trim((string)$_row_a['firstname'] . " " . (string)$_row_a['othernames'] . " " . (string)$_row_a['surname']);
                                    $_SelectedItemCount = (int)$_row_a['selected_item_count'];
                                    $_SearchText = strtolower(trim($_TeacherName . " " . (string)$_row_a['userid'] . " " . (string)$_row_a['class_name'] . " " . (string)$_row_a['batch'] . " " . (string)$_row_a['termname'] . " " . (string)$_row_a['status']));
                                ?>
                                <tr data-assignment-row data-search="<?php echo tba_esc($_SearchText); ?>">
                                    <td data-label="Actions">
                                        <div class="tba-row-actions print-hide">
                                            <a class="tba-action-btn tba-action-btn--edit" title="Set billing items" href="teacher-billing-assignment.php?set_items=<?php echo urlencode($_row_a['assignmentid']); ?>"><i class="fa fa-list"></i><span>Items</span></a>
                                            <?php if ((string)$_row_a['status'] === 'active') { ?>
                                            <a class="tba-action-btn tba-action-btn--warn" title="Deactivate assignment" onclick="return confirm('Deactivate this assignment?');" href="teacher-billing-assignment.php?deactivate_assignment=<?php echo urlencode($_row_a['assignmentid']); ?>"><i class="fa fa-ban"></i><span>Deactivate</span></a>
                                            <?php } ?>
                                            <a class="tba-action-btn tba-action-btn--danger" title="Delete assignment" onclick="return confirm('Delete this assignment permanently?');" href="teacher-billing-assignment.php?delete_assignment=<?php echo urlencode($_row_a['assignmentid']); ?>"><i class="fa fa-trash"></i><span>Delete</span></a>
                                        </div>
                                    </td>
                                    <td data-label="Teacher">
                                        <div class="tba-person">
                                            <strong><?php echo tba_esc($_TeacherName); ?></strong>
                                            <small><?php echo tba_esc($_row_a['userid']); ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Class"><?php echo tba_esc($_row_a['class_name']); ?></td>
                                    <td data-label="Semester"><?php echo tba_esc($_row_a['termname']); ?></td>
                                    <td data-label="Batch"><?php echo tba_esc($_row_a['batch']); ?></td>
                                    <td data-label="Billing Items">
                                        <?php if ($_SelectedItemCount > 0) { ?>
                                        <span class="tba-badge tba-badge--count"><?php echo number_format((int)$_SelectedItemCount); ?> selected</span>
                                        <?php } else { ?>
                                        <span class="tba-badge tba-badge--all">All in scope</span>
                                        <?php } ?>
                                    </td>
                                    <td data-label="Status"><?php echo tba_status_badge_html($_row_a['status']); ?></td>
                                    <td data-label="Date / Time"><?php echo tba_esc($_row_a['datetimeentry']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="tba-empty-state tba-empty-state--inline" data-assignment-empty hidden>
                        <h3>No assignments match this search</h3>
                        <p>Try a teacher name, class name, batch, or clear the search box.</p>
                    </div>
                    <?php } ?>
                </section>
            </section>
        </div>
    </main>
</div>
</body>
</html>
