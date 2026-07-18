<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("storekeeper-utils.php");
ensure_storekeeper_tables($con);

if (!storekeeper_can_manage_module($con, 'stores_management')) {
    header("location:" . storekeeper_landing_page());
    exit();
}

if (!function_exists('sk_student_issue_date')) {
function sk_student_issue_date($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y", $timestamp) : $value;
}
}

$_Message = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
unset($_SESSION['Message']);

$_IssueForm = array(
    'issuedate' => date('Y-m-d'),
    'studentid' => '',
    'studentlabel' => '',
    'storeitemid' => '',
    'storeitemlabel' => '',
    'quantity' => '1',
    'returnrequired' => '1',
    'expectedreturndate' => '',
    'purpose' => '',
    'issuecondition' => 'Good',
    'notes' => ''
);

$_ReturnForm = array(
    'studentissueid' => '',
    'action_status' => 'returned',
    'actualreturndate' => date('Y-m-d'),
    'returncondition' => 'Good',
    'notes' => ''
);
$_ReturnRow = null;

if (isset($_POST['save_student_issue'])) {
    $_IssueForm['issuedate'] = trim((string)(isset($_POST['issuedate']) ? $_POST['issuedate'] : date('Y-m-d')));
    $_IssueForm['studentid'] = trim((string)(isset($_POST['studentid']) ? $_POST['studentid'] : ''));
    $_IssueForm['studentlabel'] = trim((string)(isset($_POST['studentlabel']) ? $_POST['studentlabel'] : ''));
    $_IssueForm['storeitemid'] = trim((string)(isset($_POST['storeitemid']) ? $_POST['storeitemid'] : ''));
    $_IssueForm['storeitemlabel'] = trim((string)(isset($_POST['storeitemlabel']) ? $_POST['storeitemlabel'] : ''));
    $_IssueForm['quantity'] = trim((string)(isset($_POST['quantity']) ? $_POST['quantity'] : '1'));
    $_IssueForm['returnrequired'] = (isset($_POST['returnrequired']) && (string)$_POST['returnrequired'] === '1') ? '1' : '0';
    $_IssueForm['expectedreturndate'] = trim((string)(isset($_POST['expectedreturndate']) ? $_POST['expectedreturndate'] : ''));
    $_IssueForm['purpose'] = trim((string)(isset($_POST['purpose']) ? $_POST['purpose'] : ''));
    $_IssueForm['issuecondition'] = trim((string)(isset($_POST['issuecondition']) ? $_POST['issuecondition'] : 'Good'));
    $_IssueForm['notes'] = trim((string)(isset($_POST['notes']) ? $_POST['notes'] : ''));

    if ($_IssueForm['studentid'] === '' && $_IssueForm['studentlabel'] !== '') {
        $_ResolvedStudent = storekeeper_find_student_by_picker_label($con, $_IssueForm['studentlabel']);
        if ($_ResolvedStudent) {
            $_IssueForm['studentid'] = (string)$_ResolvedStudent['userid'];
        }
    }
    if ($_IssueForm['storeitemid'] === '' && $_IssueForm['storeitemlabel'] !== '') {
        $_ResolvedItem = storekeeper_find_item_by_picker_label($con, $_IssueForm['storeitemlabel']);
        if ($_ResolvedItem) {
            $_IssueForm['storeitemid'] = (string)$_ResolvedItem['storeitemid'];
        }
    }
    $_ItemRow = storekeeper_get_item_row($con, $_IssueForm['storeitemid']);
    $_StudentRow = storekeeper_get_student_row($con, $_IssueForm['studentid']);
    if (!$_StudentRow && $_IssueForm['studentlabel'] !== '') {
        $_StudentRow = storekeeper_find_student_by_picker_label($con, $_IssueForm['studentlabel']);
        if ($_StudentRow) {
            $_IssueForm['studentid'] = (string)$_StudentRow['userid'];
        }
    }
    if (!$_ItemRow && $_IssueForm['storeitemlabel'] !== '') {
        $_ItemRow = storekeeper_find_item_by_picker_label($con, $_IssueForm['storeitemlabel']);
        if ($_ItemRow) {
            $_IssueForm['storeitemid'] = (string)$_ItemRow['storeitemid'];
        }
    }

    if (!$_StudentRow || (string)$_StudentRow['status'] !== 'active') {
        $_Message = storekeeper_flash_html('error', 'Please select a valid active student.');
    } elseif (!$_ItemRow || (string)$_ItemRow['status'] !== 'active') {
        $_Message = storekeeper_flash_html('error', 'Please select a valid active item.');
    } elseif ($_IssueForm['issuedate'] === '') {
        $_Message = storekeeper_flash_html('error', 'Collection date is required.');
    } elseif ($_IssueForm['quantity'] === '' || !is_numeric($_IssueForm['quantity']) || (float)$_IssueForm['quantity'] <= 0) {
        $_Message = storekeeper_flash_html('error', 'Quantity must be a valid number greater than zero.');
    } elseif ($_IssueForm['returnrequired'] === '1' && $_IssueForm['expectedreturndate'] === '') {
        $_Message = storekeeper_flash_html('error', 'Expected return date is required when return is expected.');
    } elseif ((float)$_IssueForm['quantity'] > storekeeper_item_balance($con, $_IssueForm['storeitemid'])) {
        $_Balance = storekeeper_item_balance($con, $_IssueForm['storeitemid']);
        $_Message = storekeeper_flash_html('error', 'You cannot issue more than the available balance of ' . storekeeper_format_quantity($_Balance) . ' ' . storekeeper_esc($_ItemRow['unitname']) . '.');
    } else {
        include("code.php");
        $_StudentIssueIdEsc = mysqli_real_escape_string($con, trim((string)$code));
        $_ItemIdEsc = mysqli_real_escape_string($con, $_IssueForm['storeitemid']);
        $_StudentIdEsc = mysqli_real_escape_string($con, $_IssueForm['studentid']);
        $_IssueDateEsc = mysqli_real_escape_string($con, $_IssueForm['issuedate']);
        $_Quantity = number_format((float)$_IssueForm['quantity'], 2, '.', '');
        $_ReturnRequired = $_IssueForm['returnrequired'] === '1' ? 1 : 0;
        $_ExpectedReturnSql = "NULL";
        if ($_ReturnRequired === 1 && $_IssueForm['expectedreturndate'] !== '') {
            $_ExpectedReturnSql = "'" . mysqli_real_escape_string($con, $_IssueForm['expectedreturndate']) . "'";
        }
        $_PurposeEsc = mysqli_real_escape_string($con, $_IssueForm['purpose']);
        $_IssueConditionEsc = mysqli_real_escape_string($con, $_IssueForm['issuecondition']);
        $_NotesEsc = mysqli_real_escape_string($con, $_IssueForm['notes']);
        $_RecordedByEsc = mysqli_real_escape_string($con, isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '');

        $_SQL = mysqli_query($con, "INSERT INTO tblstorestudentissue
            (studentissueid,storeitemid,studentid,issuedate,quantity,returnrequired,expectedreturndate,actualreturndate,purpose,issuecondition,returncondition,notes,status,datetimeentry,recordedby)
            VALUES
            ('$_StudentIssueIdEsc','$_ItemIdEsc','$_StudentIdEsc','$_IssueDateEsc','$_Quantity',$_ReturnRequired,$_ExpectedReturnSql,NULL,'$_PurposeEsc','$_IssueConditionEsc','','$_NotesEsc','issued',NOW(),'$_RecordedByEsc')");
        if ($_SQL) {
            $_SESSION['Message'] = storekeeper_flash_html('success', 'Student issue saved successfully.');
            header("location:store-student-issue.php");
            exit();
        }
        $_Message = storekeeper_flash_html('error', 'Failed to save student issue: ' . storekeeper_esc(mysqli_error($con)));
    }
}

if (isset($_POST['save_student_return'])) {
    $_ReturnForm['studentissueid'] = trim((string)(isset($_POST['studentissueid']) ? $_POST['studentissueid'] : ''));
    $_ReturnForm['action_status'] = trim((string)(isset($_POST['action_status']) ? $_POST['action_status'] : 'returned'));
    $_ReturnForm['actualreturndate'] = trim((string)(isset($_POST['actualreturndate']) ? $_POST['actualreturndate'] : date('Y-m-d')));
    $_ReturnForm['returncondition'] = trim((string)(isset($_POST['returncondition']) ? $_POST['returncondition'] : 'Good'));
    $_ReturnForm['notes'] = trim((string)(isset($_POST['notes']) ? $_POST['notes'] : ''));

    $_ReturnRow = storekeeper_get_student_issue_row($con, $_ReturnForm['studentissueid']);
    if (!$_ReturnRow) {
        $_Message = storekeeper_flash_html('error', 'The selected student issue record could not be found.');
    } elseif (in_array((string)$_ReturnRow['status'], array('returned', 'void'), true)) {
        $_Message = storekeeper_flash_html('warning', 'This student issue record is already closed.');
    } elseif (!in_array($_ReturnForm['action_status'], array('returned', 'lost'), true)) {
        $_Message = storekeeper_flash_html('error', 'Please choose a valid return action.');
    } elseif ($_ReturnForm['action_status'] === 'returned' && $_ReturnForm['actualreturndate'] === '') {
        $_Message = storekeeper_flash_html('error', 'Actual return date is required when marking an item as returned.');
    } else {
        $_StudentIssueIdEsc = mysqli_real_escape_string($con, $_ReturnForm['studentissueid']);
        $_ActionStatusEsc = mysqli_real_escape_string($con, $_ReturnForm['action_status']);
        $_ReturnConditionEsc = mysqli_real_escape_string($con, $_ReturnForm['returncondition']);
        $_NotesEsc = mysqli_real_escape_string($con, $_ReturnForm['notes']);
        $_ActualReturnSql = "NULL";
        if ($_ReturnForm['action_status'] === 'returned') {
            $_ActualReturnSql = "'" . mysqli_real_escape_string($con, $_ReturnForm['actualreturndate']) . "'";
        }
        $_ExistingNotes = trim((string)$_ReturnRow['notes']);
        $_CombinedNotes = $_ExistingNotes;
        if ($_NotesEsc !== '') {
            $_CombinedNotes = trim($_ExistingNotes . ($_ExistingNotes !== '' ? ' | ' : '') . $_ReturnForm['notes']);
        }
        $_CombinedNotesEsc = mysqli_real_escape_string($con, $_CombinedNotes);

        $_SQL = mysqli_query($con, "UPDATE tblstorestudentissue
            SET status='$_ActionStatusEsc',
                actualreturndate=$_ActualReturnSql,
                returncondition='$_ReturnConditionEsc',
                notes='$_CombinedNotesEsc'
            WHERE studentissueid='$_StudentIssueIdEsc'
            LIMIT 1");
        if ($_SQL) {
            $_SESSION['Message'] = storekeeper_flash_html('success', $_ReturnForm['action_status'] === 'returned' ? 'Student return recorded successfully.' : 'Student item marked as lost/missing.');
            header("location:store-student-issue.php");
            exit();
        }
        $_Message = storekeeper_flash_html('error', 'Failed to update student issue record: ' . storekeeper_esc(mysqli_error($con)));
    }
}

if (isset($_GET['void_student_issue'])) {
    $_StudentIssueId = trim((string)$_GET['void_student_issue']);
    if ($_StudentIssueId !== '') {
        $_Row = storekeeper_get_student_issue_row($con, $_StudentIssueId);
        if ($_Row) {
            $_StudentIssueIdEsc = mysqli_real_escape_string($con, $_StudentIssueId);
            $_SQL = mysqli_query($con, "UPDATE tblstorestudentissue
                SET status='void', actualreturndate=NULL, returncondition=''
                WHERE studentissueid='$_StudentIssueIdEsc'
                LIMIT 1");
            if ($_SQL) {
                $_SESSION['Message'] = storekeeper_flash_html('warning', 'Student issue record voided successfully.');
            } else {
                $_SESSION['Message'] = storekeeper_flash_html('error', 'Failed to void student issue record: ' . storekeeper_esc(mysqli_error($con)));
            }
        }
    }
    header("location:store-student-issue.php");
    exit();
}

if (isset($_GET['return_issue'])) {
    $_ReturnId = trim((string)$_GET['return_issue']);
    $_ReturnRow = storekeeper_get_student_issue_row($con, $_ReturnId);
    if ($_ReturnRow) {
        $_ReturnForm['studentissueid'] = (string)$_ReturnRow['studentissueid'];
        $_ReturnForm['actualreturndate'] = date('Y-m-d');
        $_ReturnForm['returncondition'] = trim((string)$_ReturnRow['issuecondition']) !== '' ? (string)$_ReturnRow['issuecondition'] : 'Good';
    } else {
        $_Message = storekeeper_flash_html('error', 'The selected student issue record could not be found.');
    }
}

$_Search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$_FilterStudentId = isset($_GET['studentid']) ? trim((string)$_GET['studentid']) : '';
$_FilterStudentLabel = isset($_GET['studentlabel']) ? trim((string)$_GET['studentlabel']) : '';
$_FilterStatusInput = isset($_GET['statuslabel']) ? trim((string)$_GET['statuslabel']) : '';
if ($_FilterStatusInput === '' && isset($_GET['status'])) {
    $_FilterStatusInput = trim((string)$_GET['status']);
}
$_PopulationScopeInput = isset($_GET['population_scopelabel']) ? trim((string)$_GET['population_scopelabel']) : '';
if ($_PopulationScopeInput === '' && isset($_GET['population_scope'])) {
    $_PopulationScopeInput = trim((string)$_GET['population_scope']);
}
$_FilterStatus = storekeeper_find_student_issue_status_key($_FilterStatusInput);
$_PopulationScope = storekeeper_find_student_scope_key($_PopulationScopeInput, 'all');
$_Summary = storekeeper_dashboard_summary($con);
$_PopulationSummary = storekeeper_student_population_summary($con);
$_SelectedPopulationLabel = storekeeper_student_scope_label($_PopulationScope);
$_Students = storekeeper_active_students($con, 6000, array('scope' => $_PopulationScope));
if ($_FilterStudentId === '' && $_FilterStudentLabel !== '') {
    $_FilterStudentRow = storekeeper_find_student_by_picker_label($con, $_FilterStudentLabel, $_Students);
    if ($_FilterStudentRow) {
        $_FilterStudentId = (string)$_FilterStudentRow['userid'];
    }
}
if ($_FilterStudentLabel === '' && $_FilterStudentId !== '') {
    $_FilterStudentRow = storekeeper_get_student_row($con, $_FilterStudentId);
    if ($_FilterStudentRow) {
        $_FilterStudentLabel = storekeeper_student_picker_label($_FilterStudentRow);
    }
}
$_SelectedFilterStatusLabel = $_FilterStatus !== '' ? storekeeper_student_issue_status_label($_FilterStatus) : '';
if ($_SelectedFilterStatusLabel === '' && $_FilterStatusInput !== '') {
    $_SelectedFilterStatusLabel = $_FilterStatusInput;
}
$_Filters = array(
    'search' => $_Search,
    'studentid' => $_FilterStudentId,
    'status' => $_FilterStatus,
    'population_scope' => $_PopulationScope,
    'limit' => 300
);
$_IssueRows = storekeeper_fetch_student_issue_rows($con, $_Filters);
$_BalanceRows = storekeeper_fetch_balance_rows($con);
$_IssueItems = array();
foreach ($_BalanceRows as $_BalanceRow) {
    if ((string)$_BalanceRow['status'] === 'active') {
        $_IssueItems[] = $_BalanceRow;
    }
}
$_SelectedStudentName = storekeeper_selected_student_name($con, $_IssueForm['studentid'], $_Students);
$_SelectedStudentItemName = storekeeper_selected_item_name($con, $_IssueForm['storeitemid'], $_IssueItems);
$_SelectedStudentLabel = '';
foreach ($_Students as $_Student) {
    if ($_IssueForm['studentid'] !== '' && $_IssueForm['studentid'] === (string)$_Student['userid']) {
        $_SelectedStudentLabel = storekeeper_student_picker_label($_Student);
        break;
    }
}
if ($_SelectedStudentLabel === '' && $_IssueForm['studentlabel'] !== '') {
    $_SelectedStudentLabel = $_IssueForm['studentlabel'];
}
$_SelectedStudentItemLabel = '';
foreach ($_IssueItems as $_Item) {
    if ($_IssueForm['storeitemid'] !== '' && $_IssueForm['storeitemid'] === (string)$_Item['storeitemid']) {
        $_SelectedStudentItemLabel = storekeeper_item_picker_label($_Item);
        break;
    }
}
if ($_SelectedStudentItemLabel === '' && $_IssueForm['storeitemlabel'] !== '') {
    $_SelectedStudentItemLabel = $_IssueForm['storeitemlabel'];
}
$_SelectedStudentIssueSummary = $_SelectedStudentName . ' collecting ' . $_SelectedStudentItemName;
if ($_IssueForm['returnrequired'] === '1') {
    $_SelectedStudentIssueSummary .= ' with return expected';
}

$_VisibleTotal = count($_IssueRows);
$_VisibleOutstanding = 0;
$_VisibleReturned = 0;
$_VisibleOverdue = 0;
foreach ($_IssueRows as $_IssueRow) {
    $_DisplayStatus = storekeeper_student_issue_status($_IssueRow);
    if (in_array($_DisplayStatus, array('issued', 'awaiting_return', 'overdue'), true)) {
        $_VisibleOutstanding++;
    }
    if ($_DisplayStatus === 'returned') {
        $_VisibleReturned++;
    }
    if ($_DisplayStatus === 'overdue') {
        $_VisibleOverdue++;
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
                <span class="sk-kicker"><i class="fa fa-book"></i> Student Items Register</span>
                <h1>Issue books and store items directly to students.</h1>
                <p>Track which student collected an item, when it was collected, whether it should be returned, and when it was actually returned. The storekeeper can now work directly with day students, boarders, day boys, day girls, boarder boys, and boarder girls from here.</p>
                <div class="sk-link-grid">
                    <a class="sk-link-chip" href="storekeeper-dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a>
                    <a class="sk-link-chip" href="store-item-entry.php"><i class="fa fa-tags"></i> Item Master</a>
                    <a class="sk-link-chip" href="store-stock-receipt.php"><i class="fa fa-download"></i> Stock Receipt</a>
                    <a class="sk-link-chip" href="store-balance-report.php"><i class="fa fa-line-chart"></i> Balance Report</a>
                </div>
                <div class="sk-hero__chips" style="margin-top:16px;">
                    <span class="sk-chip"><i class="fa fa-users"></i> Current Group: <?php echo storekeeper_esc($_SelectedPopulationLabel); ?></span>
                    <span class="sk-chip"><i class="fa fa-sun-o"></i> Day Students: <?php echo number_format((int)$_PopulationSummary['day_students_total']); ?></span>
                    <span class="sk-chip"><i class="fa fa-home"></i> Boarders: <?php echo number_format((int)$_PopulationSummary['boarding_students_total']); ?></span>
                </div>
            </div>
            <div class="sk-stats">
                <article class="sk-stat">
                    <span>Items Out To Students</span>
                    <strong><?php echo number_format((int)$_Summary['student_items_out']); ?></strong>
                    <small>Student-issued items not yet returned or resolved.</small>
                </article>
                <article class="sk-stat">
                    <span>Overdue Returns</span>
                    <strong><?php echo number_format((int)$_Summary['student_items_overdue']); ?></strong>
                    <small>Returnable student items already past their expected return dates.</small>
                </article>
                <article class="sk-stat">
                    <span>This Week</span>
                    <strong><?php echo number_format((int)$_Summary['student_issue_count_week']); ?></strong>
                    <small>Student item collection records created in the last seven days.</small>
                </article>
                <article class="sk-stat">
                    <span>Accessible Students</span>
                    <strong><?php echo number_format(count($_Students)); ?></strong>
                    <small>Students currently loaded in the selected group for new issue entry.</small>
                </article>
            </div>
        </section>

        <?php if ($_Message !== "") { ?>
        <?php echo $_Message; ?>
        <?php } ?>

        <section class="sk-summary-grid">
            <article class="sk-summary-card">
                <span>All Students</span>
                <strong><?php echo number_format((int)$_PopulationSummary['student_total']); ?></strong>
                <small><a class="sk-inline-action" href="store-student-issue.php?population_scope=all">Load all students</a></small>
            </article>
            <article class="sk-summary-card">
                <span>All Day Students</span>
                <strong><?php echo number_format((int)$_PopulationSummary['day_students_total']); ?></strong>
                <small><a class="sk-inline-action" href="store-student-issue.php?population_scope=day">Load day students</a></small>
            </article>
            <article class="sk-summary-card">
                <span>All Boarders</span>
                <strong><?php echo number_format((int)$_PopulationSummary['boarding_students_total']); ?></strong>
                <small><a class="sk-inline-action" href="store-student-issue.php?population_scope=boarding">Load boarders</a></small>
            </article>
            <article class="sk-summary-card">
                <span>Students In Current Group</span>
                <strong><?php echo number_format(count($_Students)); ?></strong>
                <small><?php echo storekeeper_esc($_SelectedPopulationLabel); ?> currently loaded for issue entry and filtering.</small>
            </article>
        </section>

        <section class="sk-summary-grid">
            <article class="sk-summary-card">
                <span>Day Boys</span>
                <strong><?php echo number_format((int)$_PopulationSummary['day_boys']); ?></strong>
                <small><a class="sk-inline-action" href="store-student-issue.php?population_scope=day_boys">Load day boys</a></small>
            </article>
            <article class="sk-summary-card">
                <span>Day Girls</span>
                <strong><?php echo number_format((int)$_PopulationSummary['day_girls']); ?></strong>
                <small><a class="sk-inline-action" href="store-student-issue.php?population_scope=day_girls">Load day girls</a></small>
            </article>
            <article class="sk-summary-card">
                <span>Boarder Boys</span>
                <strong><?php echo number_format((int)$_PopulationSummary['boarding_boys']); ?></strong>
                <small><a class="sk-inline-action" href="store-student-issue.php?population_scope=boarding_boys">Load boarder boys</a></small>
            </article>
            <article class="sk-summary-card">
                <span>Boarder Girls</span>
                <strong><?php echo number_format((int)$_PopulationSummary['boarding_girls']); ?></strong>
                <small><a class="sk-inline-action" href="store-student-issue.php?population_scope=boarding_girls">Load boarder girls</a></small>
            </article>
        </section>

        <section class="sk-summary-grid">
            <article class="sk-summary-card">
                <span>Outstanding</span>
                <strong><?php echo number_format((int)$_VisibleOutstanding); ?></strong>
                <small>Visible records that are still out with students.</small>
            </article>
            <article class="sk-summary-card">
                <span>Returned</span>
                <strong><?php echo number_format((int)$_VisibleReturned); ?></strong>
                <small>Visible records already returned back into school custody.</small>
            </article>
            <article class="sk-summary-card">
                <span>Overdue</span>
                <strong><?php echo number_format((int)$_VisibleOverdue); ?></strong>
                <small>Visible returnable items needing follow-up.</small>
            </article>
            <article class="sk-summary-card">
                <span>Visible Records</span>
                <strong><?php echo number_format((int)$_VisibleTotal); ?></strong>
                <small>Student item records shown in the current table filter.</small>
            </article>
        </section>

        <div class="sk-layout">
            <section class="sk-panel">
                <div class="sk-panel__header">
                    <div>
                        <h2>Issue Item To Student</h2>
                        <p>Use this when the storekeeper hands over books, uniforms, bedding, or other store-controlled items to a student.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <?php if (empty($_Students) || empty($_IssueItems)) { ?>
                    <div class="sk-empty">You need active students and active store items before you can record student collections.</div>
                    <?php } else { ?>
                    <form method="post" class="sk-form" action="store-student-issue.php">
                        <div class="sk-form-grid">
                            <div class="sk-field">
                                <label for="issuedate">Collection Date</label>
                                <input type="date" id="issuedate" name="issuedate" value="<?php echo storekeeper_esc($_IssueForm['issuedate']); ?>" required>
                            </div>
                            <div class="sk-field">
                                <label for="quantity">Quantity</label>
                                <input type="number" id="quantity" name="quantity" value="<?php echo storekeeper_esc($_IssueForm['quantity']); ?>" min="0.01" step="0.01" required>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="studentpicker">Student</label>
                                <input type="hidden" id="studentid" name="studentid" value="<?php echo storekeeper_esc($_IssueForm['studentid']); ?>">
                                <input type="text" id="studentpicker" name="studentlabel" value="<?php echo storekeeper_esc($_SelectedStudentLabel); ?>" list="studentpickerlist" placeholder="Select or type student name" autocomplete="off" oninput="if (window.storekeeperSyncStudentIssueSummary) { window.storekeeperSyncStudentIssueSummary(); }" required>
                                <datalist id="studentpickerlist">
                                    <?php foreach ($_Students as $_Student) { ?>
                                    <option value="<?php echo storekeeper_esc(storekeeper_student_picker_label($_Student)); ?>" data-studentid="<?php echo storekeeper_esc($_Student['userid']); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="storeitempicker">Item</label>
                                <input type="hidden" id="storeitemid" name="storeitemid" value="<?php echo storekeeper_esc($_IssueForm['storeitemid']); ?>">
                                <input type="text" id="storeitempicker" name="storeitemlabel" value="<?php echo storekeeper_esc($_SelectedStudentItemLabel); ?>" list="storeitempickerlist" placeholder="Select or type item name" autocomplete="off" oninput="if (window.storekeeperSyncStudentIssueSummary) { window.storekeeperSyncStudentIssueSummary(); }" required>
                                <datalist id="storeitempickerlist">
                                    <?php foreach ($_IssueItems as $_Item) { ?>
                                    <option value="<?php echo storekeeper_esc(storekeeper_item_picker_label($_Item)); ?>" data-itemid="<?php echo storekeeper_esc($_Item['storeitemid']); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="selectedstudentissuesummary">Selected Student Issue</label>
                                <div id="selectedstudentissuesummary" class="sk-readonly-field"><?php echo storekeeper_esc($_SelectedStudentIssueSummary); ?></div>
                                <small class="sk-field-help">This shows the student and item currently selected for the issue entry.</small>
                            </div>
                            <div class="sk-field">
                                <label for="returnrequired">Return Expected?</label>
                                <label class="sk-check">
                                    <input type="checkbox" id="returnrequired" name="returnrequired" value="1" <?php echo $_IssueForm['returnrequired'] === '1' ? 'checked' : ''; ?>>
                                    <span>Yes, this item should be returned to the store.</span>
                                </label>
                                <small class="sk-field-help">Turn this on for textbooks, bedding, tools, and other reusable items.</small>
                            </div>
                            <div class="sk-field">
                                <label for="expectedreturndate">Expected Return Date</label>
                                <input type="date" id="expectedreturndate" name="expectedreturndate" value="<?php echo storekeeper_esc($_IssueForm['expectedreturndate']); ?>">
                            </div>
                            <div class="sk-field">
                                <label for="issuecondition">Condition On Issue</label>
                                <input type="text" id="issuecondition" name="issuecondition" value="<?php echo storekeeper_esc($_IssueForm['issuecondition']); ?>" placeholder="e.g. Good, New, Used">
                            </div>
                            <div class="sk-field">
                                <label for="purpose">Purpose</label>
                                <input type="text" id="purpose" name="purpose" value="<?php echo storekeeper_esc($_IssueForm['purpose']); ?>" placeholder="e.g. Textbook, House item, Uniform">
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" placeholder="Optional remarks about the item or issue arrangement."><?php echo storekeeper_esc($_IssueForm['notes']); ?></textarea>
                            </div>
                        </div>
                        <div class="sk-actions">
                            <button type="submit" name="save_student_issue" class="sk-button"><i class="fa fa-save"></i> Save Student Issue</button>
                        </div>
                    </form>
                    <?php } ?>
                </div>
            </section>

            <section class="sk-panel" id="return-panel">
                <div class="sk-panel__header">
                    <div>
                        <h2>Return / Recovery Update</h2>
                        <p>Pick a student issue record from the table below to mark it as returned or lost.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <?php if ($_ReturnRow) { ?>
                    <div class="sk-list-item" style="margin-bottom:16px;">
                        <strong><?php echo storekeeper_esc($_ReturnRow['student_name']); ?></strong>
                        <div class="sk-inline-meta">
                            <span>Item: <?php echo storekeeper_esc($_ReturnRow['itemname']); ?></span>
                            <span>Quantity: <?php echo storekeeper_format_quantity($_ReturnRow['quantity']); ?> <?php echo storekeeper_esc($_ReturnRow['unitname']); ?></span>
                            <span>Collected: <?php echo sk_student_issue_date($_ReturnRow['issuedate']); ?></span>
                        </div>
                    </div>
                    <form method="post" class="sk-form" action="store-student-issue.php#return-panel">
                        <input type="hidden" name="studentissueid" value="<?php echo storekeeper_esc($_ReturnForm['studentissueid']); ?>">
                        <div class="sk-form-grid">
                            <div class="sk-field sk-field--full">
                                <label>Action</label>
                                <div class="sk-choice-grid">
                                    <label class="sk-choice">
                                        <input type="radio" name="action_status" value="returned" <?php echo $_ReturnForm['action_status'] === 'returned' ? 'checked' : ''; ?>>
                                        <span>Returned</span>
                                    </label>
                                    <label class="sk-choice">
                                        <input type="radio" name="action_status" value="lost" <?php echo $_ReturnForm['action_status'] === 'lost' ? 'checked' : ''; ?>>
                                        <span>Lost / Missing</span>
                                    </label>
                                </div>
                            </div>
                            <div class="sk-field">
                                <label for="actualreturndate">Actual Return Date</label>
                                <input type="date" id="actualreturndate" name="actualreturndate" value="<?php echo storekeeper_esc($_ReturnForm['actualreturndate']); ?>">
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="returncondition">Condition On Return</label>
                                <input type="text" id="returncondition" name="returncondition" value="<?php echo storekeeper_esc($_ReturnForm['returncondition']); ?>" placeholder="e.g. Good, Damaged, Worn">
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="return_notes">Notes</label>
                                <textarea id="return_notes" name="notes" placeholder="Optional return or loss remarks."><?php echo storekeeper_esc($_ReturnForm['notes']); ?></textarea>
                            </div>
                        </div>
                        <div class="sk-actions">
                            <button type="submit" name="save_student_return" class="sk-button"><i class="fa fa-check-circle"></i> Save Update</button>
                            <a href="store-student-issue.php" class="sk-button--ghost"><i class="fa fa-times-circle"></i> Cancel</a>
                        </div>
                    </form>
                    <?php } else { ?>
                    <div class="sk-empty">Choose a row below using the <strong>Return / Close</strong> action to update student recovery details.</div>
                    <?php } ?>
                </div>
            </section>
        </div>

        <section class="sk-panel">
            <div class="sk-panel__header">
                <div>
                    <h2>Student Item Register</h2>
                    <p>Filter by student, search by name or item, and follow up on outstanding returns from one place.</p>
                </div>
            </div>
            <div class="sk-panel__body">
                <div class="sk-link-grid sk-link-grid--filters" style="margin-bottom:16px;">
                    <?php foreach (storekeeper_student_scope_options() as $_ScopeKey => $_ScopeLabel) { ?>
                    <a class="sk-link-chip <?php echo $_PopulationScope === $_ScopeKey ? 'sk-link-chip--active' : ''; ?>" href="store-student-issue.php?population_scope=<?php echo urlencode($_ScopeKey); ?>">
                        <?php echo storekeeper_esc($_ScopeLabel); ?>
                    </a>
                    <?php } ?>
                </div>

                <form method="get" class="sk-form" action="store-student-issue.php">
                    <div class="sk-filter-bar">
                        <div class="sk-field">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" value="<?php echo storekeeper_esc($_Search); ?>" placeholder="Student, item, purpose, or record id">
                        </div>
                        <div class="sk-field">
                            <label for="filter_studentid">Student</label>
                            <input type="hidden" id="filter_studentid" name="studentid" value="<?php echo storekeeper_esc($_FilterStudentId); ?>">
                            <input type="text" id="filter_studentpicker" name="studentlabel" value="<?php echo storekeeper_esc($_FilterStudentLabel); ?>" list="filter-studentpickerlist" placeholder="All students" autocomplete="off">
                            <datalist id="filter-studentpickerlist">
                                <?php foreach ($_Students as $_Student) { ?>
                                <option value="<?php echo storekeeper_esc(storekeeper_student_picker_label($_Student)); ?>" data-studentid="<?php echo storekeeper_esc($_Student['userid']); ?>">
                                <?php } ?>
                            </datalist>
                        </div>
                        <div class="sk-field">
                            <label for="status">Status</label>
                            <input type="hidden" id="status" name="status" value="<?php echo storekeeper_esc($_FilterStatus); ?>">
                            <input type="text" id="statuslabel" name="statuslabel" value="<?php echo storekeeper_esc($_SelectedFilterStatusLabel); ?>" list="filter-status-list" placeholder="All statuses" autocomplete="off">
                            <datalist id="filter-status-list">
                                <?php foreach (storekeeper_student_issue_status_options() as $_StatusKey => $_StatusLabel) { ?>
                                <option value="<?php echo storekeeper_esc($_StatusLabel); ?>" data-status="<?php echo storekeeper_esc($_StatusKey); ?>">
                                <?php } ?>
                            </datalist>
                        </div>
                        <div class="sk-field">
                            <label for="population_scope">Student Group</label>
                            <input type="hidden" id="population_scope" name="population_scope" value="<?php echo storekeeper_esc($_PopulationScope); ?>">
                            <input type="text" id="population_scopelabel" name="population_scopelabel" value="<?php echo storekeeper_esc($_SelectedPopulationLabel); ?>" list="population-scope-list" placeholder="All students" autocomplete="off">
                            <datalist id="population-scope-list">
                                <?php foreach (storekeeper_student_scope_options() as $_ScopeKey => $_ScopeLabel) { ?>
                                <option value="<?php echo storekeeper_esc($_ScopeLabel); ?>" data-scope="<?php echo storekeeper_esc($_ScopeKey); ?>">
                                <?php } ?>
                            </datalist>
                        </div>
                        <div class="sk-actions">
                            <button type="submit" class="sk-button"><i class="fa fa-filter"></i> Apply Filter</button>
                            <a href="store-student-issue.php" class="sk-button--ghost"><i class="fa fa-refresh"></i> Reset</a>
                        </div>
                    </div>
                </form>

                <?php if (empty($_IssueRows)) { ?>
                <div class="sk-empty" style="margin-top:16px;">No student item records matched the current filter.</div>
                <?php } else { ?>
                <div class="sk-table-wrap" style="margin-top:16px;">
                    <table class="sk-table">
                        <thead>
                            <tr>
                                <th>Actions</th>
                                <th>Student</th>
                                <th>Student Group</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Collected</th>
                                <th>Expected Return</th>
                                <th>Actual Return</th>
                                <th>Purpose</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_IssueRows as $_Row) { ?>
                            <tr>
                                <td>
                                    <div class="sk-actions">
                                        <?php if (in_array((string)$_Row['status'], array('issued', 'lost'), true)) { ?>
                                        <a class="sk-inline-action" href="store-student-issue.php?return_issue=<?php echo urlencode($_Row['studentissueid']); ?>#return-panel"><i class="fa fa-undo"></i> Return / Close</a>
                                        <?php } ?>
                                        <?php if ((string)$_Row['status'] !== 'void') { ?>
                                        <a class="sk-inline-action" onclick="return confirm('Void this student issue record?');" href="store-student-issue.php?void_student_issue=<?php echo urlencode($_Row['studentissueid']); ?>"><i class="fa fa-ban"></i> Void</a>
                                        <?php } ?>
                                        <a class="sk-inline-action" href="student-store-records.php?studentid=<?php echo urlencode($_Row['studentid']); ?>"><i class="fa fa-user"></i> Student View</a>
                                    </div>
                                </td>
                                <td>
                                    <?php echo storekeeper_esc($_Row['student_name']); ?>
                                    <small><?php echo storekeeper_esc($_Row['studentid']); ?></small>
                                </td>
                                <td><?php echo storekeeper_esc($_Row['_population_label']); ?></td>
                                <td>
                                    <?php echo storekeeper_esc($_Row['itemname']); ?>
                                    <small><?php echo storekeeper_esc($_Row['unitname']); ?></small>
                                </td>
                                <td><?php echo storekeeper_format_quantity($_Row['quantity']); ?></td>
                                <td>
                                    <?php echo sk_student_issue_date($_Row['issuedate']); ?>
                                    <?php if (trim((string)$_Row['issuecondition']) !== '') { ?>
                                    <small>Condition: <?php echo storekeeper_esc($_Row['issuecondition']); ?></small>
                                    <?php } ?>
                                </td>
                                <td><?php echo sk_student_issue_date($_Row['expectedreturndate']); ?></td>
                                <td>
                                    <?php echo sk_student_issue_date($_Row['actualreturndate']); ?>
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
<script>
(function () {
    var form = document.querySelector('form[action="store-student-issue.php"]');
    if (!form) {
        return;
    }

    var studentIdField = form.querySelector('#studentid');
    var studentField = form.querySelector('#studentpicker');
    var itemIdField = form.querySelector('#storeitemid');
    var itemField = form.querySelector('#storeitempicker');
    var returnRequiredField = form.querySelector('#returnrequired');
    var expectedReturnField = form.querySelector('#expectedreturndate');
    var summaryField = form.querySelector('#selectedstudentissuesummary');
    var studentList = document.getElementById('studentpickerlist');
    var itemList = document.getElementById('storeitempickerlist');

    function safeTrim(value) {
        return (value || '').replace(/^\s+|\s+$/g, '');
    }

    function setNodeText(node, value) {
        if (!node) {
            return;
        }
        if (typeof node.textContent !== 'undefined') {
            node.textContent = value;
        } else {
            node.innerText = value;
        }
    }

    function findListOptionByValue(list, value) {
        if (!list) {
            return null;
        }
        var normalizedValue = safeTrim(value).toLowerCase();
        if (normalizedValue === '') {
            return null;
        }
        for (var optionIndex = 0; optionIndex < list.options.length; optionIndex++) {
            var option = list.options[optionIndex];
            if (safeTrim(option.value).toLowerCase() === normalizedValue) {
                return option;
            }
        }
        return null;
    }

    function syncHiddenStudentId() {
        if (!studentIdField) {
            return;
        }
        var matchedOption = findListOptionByValue(studentList, studentField ? studentField.value : '');
        studentIdField.value = matchedOption ? (matchedOption.getAttribute('data-studentid') || '') : '';
    }

    function syncHiddenItemId() {
        if (!itemIdField) {
            return;
        }
        var matchedOption = findListOptionByValue(itemList, itemField ? itemField.value : '');
        itemIdField.value = matchedOption ? (matchedOption.getAttribute('data-itemid') || '') : '';
    }

    function currentStudentLabel() {
        if (!studentField) {
            return 'No student selected yet';
        }
        var studentValue = safeTrim(studentField.value);
        if (studentValue === '') {
            return 'No student selected yet';
        }
        if (studentValue.indexOf(' - ') > -1) {
            var studentParts = studentValue.split(' - ');
            if (studentParts.length > 1 && safeTrim(studentParts[1]) !== '') {
                return safeTrim(studentParts[1]);
            }
            return safeTrim(studentParts[0]);
        }
        return studentValue;
    }

    function currentItemLabel() {
        if (!itemField) {
            return 'No item selected yet';
        }
        var itemValue = safeTrim(itemField.value);
        if (itemValue === '') {
            return 'No item selected yet';
        }
        if (itemValue.indexOf(' (') > -1) {
            itemValue = itemValue.split(' (')[0];
        }
        itemValue = safeTrim(itemValue);
        return itemValue !== '' ? itemValue : 'No item selected yet';
    }

    function isReturnExpected() {
        if (!returnRequiredField) {
            return false;
        }
        if (returnRequiredField.type === 'checkbox') {
            return !!returnRequiredField.checked;
        }
        return returnRequiredField.value === '1';
    }

    function syncReturnExpectedState() {
        if (!expectedReturnField) {
            return;
        }
        var returnExpected = isReturnExpected();
        expectedReturnField.disabled = !returnExpected;
        expectedReturnField.required = returnExpected;
        if (!returnExpected) {
            expectedReturnField.value = '';
        }
    }

    function syncStudentIssueSummary() {
        syncHiddenStudentId();
        syncHiddenItemId();
        syncReturnExpectedState();
        if (!summaryField) {
            return;
        }
        var studentName = currentStudentLabel();
        var itemName = currentItemLabel();
        var summary = studentName + ' collecting ' + itemName;
        if (isReturnExpected()) {
            summary += ' with return expected';
        }
        setNodeText(summaryField, summary);
    }

    window.storekeeperSyncStudentIssueSummary = syncStudentIssueSummary;
    if (studentField) {
        studentField.addEventListener('change', syncStudentIssueSummary);
        studentField.addEventListener('blur', syncStudentIssueSummary);
    }
    if (itemField) {
        itemField.addEventListener('change', syncStudentIssueSummary);
        itemField.addEventListener('blur', syncStudentIssueSummary);
    }
    if (returnRequiredField) {
        returnRequiredField.addEventListener('change', syncStudentIssueSummary);
        returnRequiredField.addEventListener('input', syncStudentIssueSummary);
    }
    form.addEventListener('submit', syncStudentIssueSummary);
    syncStudentIssueSummary();
})();

(function () {
    var form = document.querySelector('form[action="store-student-issue.php#return-panel"]');
    if (!form) {
        return;
    }

    var actionFields = form.querySelectorAll('input[name="action_status"]');
    var actualReturnField = form.querySelector('#actualreturndate');

    function selectedAction() {
        for (var actionIndex = 0; actionIndex < actionFields.length; actionIndex++) {
            if (actionFields[actionIndex].checked) {
                return actionFields[actionIndex].value;
            }
        }
        return 'returned';
    }

    function syncReturnActionState() {
        if (!actualReturnField) {
            return;
        }
        var isReturned = selectedAction() === 'returned';
        actualReturnField.disabled = !isReturned;
        actualReturnField.required = isReturned;
        if (!isReturned) {
            actualReturnField.value = '';
        }
    }

    for (var actionIndex = 0; actionIndex < actionFields.length; actionIndex++) {
        actionFields[actionIndex].addEventListener('change', syncReturnActionState);
    }
    syncReturnActionState();
})();

(function () {
    var form = document.querySelector('form[method="get"][action="store-student-issue.php"]');
    if (!form) {
        return;
    }

    var studentIdField = form.querySelector('#filter_studentid');
    var studentField = form.querySelector('#filter_studentpicker');
    var studentList = document.getElementById('filter-studentpickerlist');
    var statusField = form.querySelector('#status');
    var statusLabelField = form.querySelector('#statuslabel');
    var statusList = document.getElementById('filter-status-list');
    var scopeField = form.querySelector('#population_scope');
    var scopeLabelField = form.querySelector('#population_scopelabel');
    var scopeList = document.getElementById('population-scope-list');

    function safeTrim(value) {
        return (value || '').replace(/^\s+|\s+$/g, '');
    }

    function findListOptionByValue(list, value) {
        if (!list) {
            return null;
        }
        var normalizedValue = safeTrim(value).toLowerCase();
        if (normalizedValue === '') {
            return null;
        }
        for (var optionIndex = 0; optionIndex < list.options.length; optionIndex++) {
            var option = list.options[optionIndex];
            if (safeTrim(option.value).toLowerCase() === normalizedValue) {
                return option;
            }
        }
        return null;
    }

    function syncFilterFields() {
        var matchedStudent = findListOptionByValue(studentList, studentField ? studentField.value : '');
        if (studentIdField) {
            studentIdField.value = matchedStudent ? (matchedStudent.getAttribute('data-studentid') || '') : '';
        }

        var matchedStatus = findListOptionByValue(statusList, statusLabelField ? statusLabelField.value : '');
        if (statusField) {
            statusField.value = matchedStatus ? (matchedStatus.getAttribute('data-status') || '') : '';
        }

        var matchedScope = findListOptionByValue(scopeList, scopeLabelField ? scopeLabelField.value : '');
        if (scopeField) {
            scopeField.value = matchedScope ? (matchedScope.getAttribute('data-scope') || '') : '';
        }
    }

    if (studentField) {
        studentField.addEventListener('change', syncFilterFields);
        studentField.addEventListener('blur', syncFilterFields);
    }
    if (statusLabelField) {
        statusLabelField.addEventListener('change', syncFilterFields);
        statusLabelField.addEventListener('blur', syncFilterFields);
    }
    if (scopeLabelField) {
        scopeLabelField.addEventListener('change', syncFilterFields);
        scopeLabelField.addEventListener('blur', syncFilterFields);
    }
    form.addEventListener('submit', syncFilterFields);
    syncFilterFields();
})();
</script>
</body>
</html>
