<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("matron-utils.php");
ensure_storekeeper_tables($con);
ensure_matron_tables($con);

$_AccessLevel = isset($_SESSION['ACCESSLEVEL']) ? trim((string)$_SESSION['ACCESSLEVEL']) : '';
$_SystemType = isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
$_AllowedSystemTypes = array('Teacher', 'AssistantHeadAcademic');
if (!($_AccessLevel === "user" && in_array($_SystemType, $_AllowedSystemTypes, true))) {
    header("location:" . (function_exists('um_home_link_for_session') ? um_home_link_for_session() : (function_exists('class_teacher_landing_page') ? class_teacher_landing_page() : "index.php")));
    exit();
}

if (!function_exists('tsr_esc')) {
function tsr_esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if (!function_exists('tsr_flash')) {
function tsr_flash($tone, $message)
{
    $tone = strtolower(trim((string)$tone));
    if (!in_array($tone, array('success', 'error', 'warning', 'info'), true)) {
        $tone = 'info';
    }
    return "<div class='sk-flash sk-flash--" . tsr_esc($tone) . "'><div class='sk-flash__icon'><i class='fa fa-info-circle'></i></div><div class='sk-flash__body'>" . tsr_esc($message) . "</div></div>";
}
}

if (!function_exists('tsr_date')) {
function tsr_date($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y", $timestamp) : $value;
}
}

if (!function_exists('tsr_requestor_profile')) {
function tsr_requestor_profile($systemType)
{
    $systemType = trim((string)$systemType);
    if ($systemType === 'AssistantHeadAcademic') {
        return array(
            'origin' => 'assistant_head',
            'role_label' => 'Assistant Head',
            'page_kicker' => 'Assistant Head Store Requests',
            'dashboard_href' => 'assistant-head-academics-page.php',
            'dashboard_label' => 'Assistant Head Dashboard',
            'description' => 'Use this page to ask for books, office supplies, teaching materials, and other active store items needed for academic work or school duties.'
        );
    }

    return array(
        'origin' => 'teacher',
        'role_label' => 'Teacher',
        'page_kicker' => 'Teacher Store Requests',
        'dashboard_href' => 'teacher-page.php',
        'dashboard_label' => 'Teacher Dashboard',
        'description' => 'Use this page to ask for chalk, books, lab items, office supplies, and other active store items you need for class work or school duties.'
    );
}
}

$_Message = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : '';
unset($_SESSION['Message']);

$_RequestProfile = tsr_requestor_profile($_SystemType);
$_RequestOrigin = $_RequestProfile['origin'];
$_RequesterId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$_RequesterName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : $_RequestProfile['role_label'];
$_TodayDay = matron_normalize_day_name(date('l'), 'Monday');
$_Catalog = matron_request_catalog_context($con, $_RequestOrigin, 500);
$_ItemRows = isset($_Catalog['rows']) && is_array($_Catalog['rows']) ? $_Catalog['rows'] : array();

$_Form = array(
    'requestdate' => date('Y-m-d'),
    'needbydate' => '',
    'weekstartdate' => matron_week_start_date(date('Y-m-d')),
    'dayname' => $_TodayDay,
    'mealtime' => 'Lunch',
    'storeitemid' => '',
    'quantity' => '1',
    'purpose' => '',
    'notes' => ''
);

if (isset($_POST['save_teacher_requisition'])) {
    $_Form['requestdate'] = trim((string)(isset($_POST['requestdate']) ? $_POST['requestdate'] : date('Y-m-d')));
    $_Form['needbydate'] = trim((string)(isset($_POST['needbydate']) ? $_POST['needbydate'] : ''));
    $_Form['storeitemid'] = trim((string)(isset($_POST['storeitemid']) ? $_POST['storeitemid'] : ''));
    $_Form['quantity'] = trim((string)(isset($_POST['quantity']) ? $_POST['quantity'] : '1'));
    $_Form['purpose'] = trim((string)(isset($_POST['purpose']) ? $_POST['purpose'] : ''));
    $_Form['notes'] = trim((string)(isset($_POST['notes']) ? $_POST['notes'] : ''));
    $_PlanningSeedDate = $_Form['needbydate'] !== '' ? $_Form['needbydate'] : $_Form['requestdate'];
    $_PlanningTimestamp = strtotime($_PlanningSeedDate);
    if (!$_PlanningTimestamp) {
        $_PlanningTimestamp = strtotime(date('Y-m-d'));
    }
    $_PlanningDate = date('Y-m-d', $_PlanningTimestamp);
    $_Form['weekstartdate'] = matron_week_start_date($_PlanningDate);
    $_Form['dayname'] = matron_normalize_day_name(date('l', $_PlanningTimestamp), $_TodayDay);
    $_Form['mealtime'] = 'Lunch';

    $_StoreItem = storekeeper_get_item_row($con, $_Form['storeitemid']);
    if (!matron_can_request_store_item($con, $_StoreItem, $_RequestOrigin)) {
        $_Message = tsr_flash('error', 'Please choose a valid store item.');
    } elseif ($_Form['requestdate'] === '') {
        $_Message = tsr_flash('error', 'Request date is required.');
    } elseif ($_Form['quantity'] === '' || !is_numeric($_Form['quantity']) || (float)$_Form['quantity'] <= 0) {
        $_Message = tsr_flash('error', 'Quantity must be a valid number greater than zero.');
    } elseif ($_Form['purpose'] === '') {
        $_Message = tsr_flash('error', 'Please state what the item is needed for.');
    } else {
        include("code.php");
        $_RequisitionIdEsc = mysqli_real_escape_string($con, trim((string)$code));
        $_StoreItemEsc = mysqli_real_escape_string($con, $_Form['storeitemid']);
        $_RequestDateEsc = mysqli_real_escape_string($con, $_Form['requestdate']);
        $_NeedBySql = $_Form['needbydate'] !== '' ? "'" . mysqli_real_escape_string($con, $_Form['needbydate']) . "'" : "NULL";
        $_WeekStartEsc = mysqli_real_escape_string($con, $_Form['weekstartdate']);
        $_DayNameEsc = mysqli_real_escape_string($con, $_Form['dayname']);
        $_MealTimeEsc = mysqli_real_escape_string($con, $_Form['mealtime']);
        $_QuantitySql = number_format((float)$_Form['quantity'], 2, '.', '');
        $_PurposeEsc = mysqli_real_escape_string($con, $_Form['purpose']);
        $_NotesEsc = mysqli_real_escape_string($con, $_Form['notes']);
        $_RequesterIdEsc = mysqli_real_escape_string($con, $_RequesterId);
        $_OriginEsc = mysqli_real_escape_string($con, $_RequestOrigin);

        $_SQL = @mysqli_query($con, "INSERT INTO tblmatronrequisition
            (requisitionid,storeitemid,requestdate,needbydate,weekstartdate,dayname,mealtime,quantity,purpose,notes,requestorigin,status,decisionnote,requestedby,decisionby,decisiondatetime,fulfilledissueid,datetimeentry)
            VALUES
            ('$_RequisitionIdEsc','$_StoreItemEsc','$_RequestDateEsc',$_NeedBySql,'$_WeekStartEsc','$_DayNameEsc','$_MealTimeEsc','$_QuantitySql','$_PurposeEsc','$_NotesEsc','$_OriginEsc','pending','', '$_RequesterIdEsc',NULL,NULL,NULL,NOW())");
        if ($_SQL) {
            $_SESSION['Message'] = tsr_flash('success', 'Your store requisition has been sent successfully.');
            header("location:teacher-store-requisition.php#teacher-request-register");
            exit();
        }
        $_Message = tsr_flash('error', 'The requisition could not be saved right now.');
    }
}

if (isset($_POST['cancel_teacher_requisition'])) {
    $_RequisitionId = trim((string)(isset($_POST['requisitionid']) ? $_POST['requisitionid'] : ''));
    if ($_RequisitionId !== '') {
        $_RequisitionIdEsc = mysqli_real_escape_string($con, $_RequisitionId);
        $_RequesterIdEsc = mysqli_real_escape_string($con, $_RequesterId);
        $_OriginEsc = mysqli_real_escape_string($con, $_RequestOrigin);
        @mysqli_query($con, "UPDATE tblmatronrequisition
            SET status='cancelled',
                decisionnote='Cancelled by the requester.',
                decisionby='$_RequesterIdEsc',
                decisiondatetime=NOW()
            WHERE requisitionid='$_RequisitionIdEsc'
              AND requestedby='$_RequesterIdEsc'
              AND COALESCE(NULLIF(TRIM(requestorigin), ''), 'matron')='$_OriginEsc'
              AND status='pending'
            LIMIT 1");
        $_SESSION['Message'] = mysqli_affected_rows($con) > 0
            ? tsr_flash('warning', 'The requisition was cancelled.')
            : tsr_flash('error', 'That requisition could not be cancelled.');
    }
    header("location:teacher-store-requisition.php#teacher-request-register");
    exit();
}

$_TeacherRows = matron_fetch_requisition_rows($con, array(
    'requestedby' => $_RequesterId,
    'requestorigin' => $_RequestOrigin,
    'limit' => 120
));
$_Summary = array(
    'total' => count($_TeacherRows),
    'pending' => 0,
    'awaiting_headmaster' => 0,
    'approved' => 0,
    'issued' => 0,
    'rejected' => 0,
    'cancelled' => 0
);
foreach ($_TeacherRows as $_Row) {
    $_StatusKey = strtolower(trim((string)(isset($_Row['status']) ? $_Row['status'] : '')));
    if (isset($_Summary[$_StatusKey])) {
        $_Summary[$_StatusKey]++;
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
<div class="header"><?php include("menu.php"); ?></div>
<main class="sk-main">
    <div class="sk-shell">
        <section class="sk-hero">
            <div>
                <span class="sk-kicker"><i class="fa fa-archive"></i> <?php echo tsr_esc($_RequestProfile['page_kicker']); ?></span>
                <h1>Request items from the school store.</h1>
                <p><?php echo tsr_esc($_RequestProfile['description']); ?></p>
                <div class="sk-link-grid">
                    <a class="sk-link-chip" href="<?php echo tsr_esc($_RequestProfile['dashboard_href']); ?>"><i class="fa fa-arrow-left"></i> Back to <?php echo tsr_esc($_RequestProfile['dashboard_label']); ?></a>
                    <a class="sk-link-chip" href="#teacher-request-form"><i class="fa fa-plus-circle"></i> New Request</a>
                    <a class="sk-link-chip" href="#teacher-request-register"><i class="fa fa-list"></i> My Request Register</a>
                </div>
                <div class="sk-hero__chips" style="margin-top:16px;">
                    <span class="sk-chip"><i class="fa fa-clock-o"></i> Waiting at Store: <?php echo number_format((int)$_Summary['pending']); ?></span>
                    <span class="sk-chip"><i class="fa fa-user-circle-o"></i> Waiting for Head: <?php echo number_format((int)$_Summary['awaiting_headmaster']); ?></span>
                    <span class="sk-chip"><i class="fa fa-check-circle"></i> Final Approved: <?php echo number_format((int)$_Summary['approved']); ?></span>
                    <span class="sk-chip"><i class="fa fa-archive"></i> Active Store Items: <?php echo number_format(count($_ItemRows)); ?></span>
                </div>
            </div>
            <div class="sk-stats">
                <article class="sk-stat">
                    <span>My Requests</span>
                    <strong><?php echo number_format((int)$_Summary['total']); ?></strong>
                    <small>All store requisitions you have raised so far.</small>
                </article>
                <article class="sk-stat">
                    <span>Waiting at Store</span>
                    <strong><?php echo number_format((int)$_Summary['pending']); ?></strong>
                    <small>Requests the storekeeper has not reviewed yet.</small>
                </article>
                <article class="sk-stat">
                    <span>Waiting for Head</span>
                    <strong><?php echo number_format((int)$_Summary['awaiting_headmaster']); ?></strong>
                    <small>Requests that have moved on for final approval.</small>
                </article>
                <article class="sk-stat">
                    <span>Issued</span>
                    <strong><?php echo number_format((int)$_Summary['issued']); ?></strong>
                    <small>Requests already released from the store.</small>
                </article>
            </div>
        </section>

        <?php if ($_Message !== '') { ?>
        <?php echo $_Message; ?>
        <?php } ?>

        <section class="sk-panel" id="teacher-request-form">
            <div class="sk-panel__header">
                <div>
                    <h2>New Store Request</h2>
                    <p>Tell the store what you need and what it is for. Add a need-by date only when it matters.</p>
                </div>
            </div>
            <div class="sk-panel__body">
                <?php if (empty($_ItemRows)) { ?>
                <div class="sk-empty">There are no active store items available right now.</div>
                <?php } else { ?>
                <form method="post" action="teacher-store-requisition.php#teacher-request-form">
                    <div class="sk-form-grid">
                        <div class="sk-field">
                            <label for="teacher_requestdate">Request Date</label>
                            <input id="teacher_requestdate" type="date" name="requestdate" value="<?php echo tsr_esc($_Form['requestdate']); ?>" required>
                        </div>
                        <div class="sk-field">
                            <label for="teacher_needbydate">Need By Date</label>
                            <input id="teacher_needbydate" type="date" name="needbydate" value="<?php echo tsr_esc($_Form['needbydate']); ?>">
                        </div>
                        <div class="sk-field sk-field--full">
                            <label for="teacher_storeitemid">Store Item</label>
                            <select id="teacher_storeitemid" name="storeitemid" required>
                                <option value="">Choose item</option>
                                <?php foreach ($_ItemRows as $_ItemRow) { ?>
                                <option value="<?php echo tsr_esc($_ItemRow['storeitemid']); ?>"<?php echo $_Form['storeitemid'] === (string)$_ItemRow['storeitemid'] ? ' selected' : ''; ?>><?php echo tsr_esc(storekeeper_item_picker_label($_ItemRow)); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="sk-field">
                            <label for="teacher_quantity">Quantity</label>
                            <input id="teacher_quantity" type="number" step="0.01" min="0.01" name="quantity" value="<?php echo tsr_esc($_Form['quantity']); ?>" required>
                        </div>
                        <div class="sk-field sk-field--full">
                            <label for="teacher_purpose">Purpose</label>
                            <input id="teacher_purpose" type="text" name="purpose" value="<?php echo tsr_esc($_Form['purpose']); ?>" placeholder="Example: Chalk for Form 2 quiz, graph books for science practical, markers for staff meeting." required>
                        </div>
                        <div class="sk-field sk-field--full">
                            <label for="teacher_notes">Notes</label>
                            <textarea id="teacher_notes" name="notes" placeholder="Optional note about urgency, class, or planned use."><?php echo tsr_esc($_Form['notes']); ?></textarea>
                        </div>
                    </div>
                    <div class="sk-actions">
                        <button type="submit" name="save_teacher_requisition" class="sk-button"><i class="fa fa-send"></i> Send Request</button>
                    </div>
                </form>
                <?php } ?>
            </div>
        </section>

        <section class="sk-panel" id="teacher-request-register">
            <div class="sk-panel__header">
                <div>
                    <h2>My Request Register</h2>
                    <p>Track every store requisition you have sent and the stage it has reached.</p>
                </div>
            </div>
            <div class="sk-panel__body">
                <?php if (empty($_TeacherRows)) { ?>
                <div class="sk-empty">You have not sent any store requisition yet.</div>
                <?php } else { ?>
                <div class="sk-table-wrap">
                    <table class="sk-table">
                        <thead>
                            <tr>
                                <th>Actions</th>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Need By</th>
                                <th>Purpose</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_TeacherRows as $_Row) { ?>
                            <tr>
                                <td>
                                    <?php if ((string)$_Row['status'] === 'pending') { ?>
                                    <form method="post" action="teacher-store-requisition.php#teacher-request-register" class="matron-inline-form">
                                        <input type="hidden" name="requisitionid" value="<?php echo tsr_esc($_Row['requisitionid']); ?>">
                                        <button type="submit" name="cancel_teacher_requisition" class="matron-inline-button" onclick="return confirm('Cancel this requisition?');"><i class="fa fa-ban"></i> Cancel</button>
                                    </form>
                                    <?php } else { ?>
                                    <span class="sk-muted">No action</span>
                                    <?php } ?>
                                </td>
                                <td><?php echo tsr_esc(tsr_date($_Row['requestdate'])); ?></td>
                                <td>
                                    <?php echo tsr_esc($_Row['itemname']); ?>
                                    <small><?php echo tsr_esc($_RequesterName); ?></small>
                                </td>
                                <td><?php echo tsr_esc(storekeeper_format_quantity($_Row['quantity'])); ?> <?php echo tsr_esc($_Row['unitname']); ?></td>
                                <td><?php echo tsr_esc(tsr_date($_Row['needbydate'])); ?></td>
                                <td>
                                    <strong class="matron-cell-title"><?php echo tsr_esc($_Row['purpose']); ?></strong>
                                    <?php if (trim((string)$_Row['notes']) !== '') { ?>
                                    <small><?php echo tsr_esc($_Row['notes']); ?></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php echo matron_requisition_badge_html($_Row['status']); ?>
                                    <?php if (trim((string)$_Row['stage_note']) !== '') { ?>
                                    <small><?php echo tsr_esc($_Row['stage_note']); ?></small>
                                    <?php } ?>
                                    <?php if (trim((string)$_Row['head_decision_by_name']) !== '') { ?>
                                    <small>Head: <?php echo tsr_esc($_Row['head_decision_by_name']); ?></small>
                                    <?php } ?>
                                </td>
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
