<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("matron-utils.php");
ensure_house_tables($con);
ensure_storekeeper_tables($con);
ensure_matron_tables($con);

if (!matron_can_manage_module($con, 'matron_management')) {
    $_SESSION['Message'] = function_exists('storekeeper_flash_html')
        ? storekeeper_flash_html('error', 'You do not have access to the matron dashboard.')
        : "<div style='color:red;text-align:center;padding:8px;'>You do not have access to the matron dashboard.</div>";
    header("location:" . matron_landing_page());
    exit();
}

if (!function_exists('matron_dashboard_date')) {
function matron_dashboard_date($value)
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

$_MenuWeekStart = isset($_GET['menu_week']) ? matron_week_start_date((string)$_GET['menu_week']) : matron_week_start_date(date('Y-m-d'));
$_CurrentWeekStart = matron_week_start_date(date('Y-m-d'));
$_UserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$_FoodCatalog = matron_store_catalog_context($con, 300);
$_FoodCatalogUsesFallback = !empty($_FoodCatalog['uses_fallback']);
$_FoodCatalogMessage = isset($_FoodCatalog['message']) ? trim((string)$_FoodCatalog['message']) : '';
$_AllowedPurposeModes = array('meal_slot', 'kitchen_cleaning', 'special_diet', 'emergency_top_up', 'custom');
$_MenuAudienceOptions = matron_menu_audience_options();

$_RequisitionForm = array(
    'requestdate' => date('Y-m-d'),
    'needbydate' => '',
    'weekstartdate' => $_MenuWeekStart,
    'dayname' => 'Monday',
    'mealtime' => 'Breakfast',
    'storeitemid' => '',
    'storeitemlabel' => '',
    'quantity' => '1',
    'purpose' => '',
    'purposemode' => 'meal_slot',
    'notes' => ''
);

$_MenuForm = array(
    'weekstartdate' => $_MenuWeekStart,
    'dayname' => 'Monday',
    'mealtime' => 'Breakfast',
    'audience' => 'student',
    'menutitle' => '',
    'menudetails' => '',
    'notes' => ''
);

if (isset($_POST['save_requisition']) && matron_can_create_requisition($con)) {
    $_RequisitionForm['requestdate'] = trim((string)(isset($_POST['requestdate']) ? $_POST['requestdate'] : date('Y-m-d')));
    $_RequisitionForm['needbydate'] = trim((string)(isset($_POST['needbydate']) ? $_POST['needbydate'] : ''));
    $_RequisitionForm['weekstartdate'] = matron_week_start_date(isset($_POST['weekstartdate']) ? (string)$_POST['weekstartdate'] : $_MenuWeekStart);
    $_RequisitionForm['dayname'] = matron_normalize_day_name(isset($_POST['dayname']) ? (string)$_POST['dayname'] : 'Monday', 'Monday');
    $_RequisitionForm['mealtime'] = matron_normalize_meal_name(isset($_POST['mealtime']) ? (string)$_POST['mealtime'] : 'Breakfast', 'Breakfast');
    $_RequisitionForm['storeitemid'] = trim((string)(isset($_POST['storeitemid']) ? $_POST['storeitemid'] : ''));
    $_RequisitionForm['storeitemlabel'] = trim((string)(isset($_POST['storeitemlabel']) ? $_POST['storeitemlabel'] : ''));
    $_RequisitionForm['quantity'] = trim((string)(isset($_POST['quantity']) ? $_POST['quantity'] : '1'));
    $_RequisitionForm['purpose'] = trim((string)(isset($_POST['purpose']) ? $_POST['purpose'] : ''));
    $_RequisitionForm['purposemode'] = trim((string)(isset($_POST['purposemode']) ? $_POST['purposemode'] : 'meal_slot'));
    $_RequisitionForm['notes'] = trim((string)(isset($_POST['notes']) ? $_POST['notes'] : ''));
    if (!in_array($_RequisitionForm['purposemode'], $_AllowedPurposeModes, true)) {
        $_RequisitionForm['purposemode'] = 'custom';
    }

    $_ValidDays = matron_menu_day_options();
    $_ValidMeals = matron_meal_options();
    if ($_RequisitionForm['storeitemid'] === '' && $_RequisitionForm['storeitemlabel'] !== '') {
        $_ResolvedItemFromLabel = storekeeper_find_item_by_picker_label($con, $_RequisitionForm['storeitemlabel'], isset($_FoodCatalog['rows']) ? $_FoodCatalog['rows'] : array());
        if ($_ResolvedItemFromLabel) {
            $_RequisitionForm['storeitemid'] = (string)$_ResolvedItemFromLabel['storeitemid'];
        }
    }
    $_StoreItem = storekeeper_get_item_row($con, $_RequisitionForm['storeitemid']);
    if (!$_StoreItem && $_RequisitionForm['storeitemlabel'] !== '') {
        $_StoreItem = storekeeper_find_item_by_picker_label($con, $_RequisitionForm['storeitemlabel'], isset($_FoodCatalog['rows']) ? $_FoodCatalog['rows'] : array());
        if ($_StoreItem) {
            $_RequisitionForm['storeitemid'] = (string)$_StoreItem['storeitemid'];
        }
    }
    $_StoreItemLabel = $_FoodCatalogUsesFallback ? 'active store item' : 'active food or kitchen item from the store';

    if (!matron_can_request_store_item($con, $_StoreItem)) {
        $_Message = storekeeper_flash_html('error', 'Please select a valid ' . $_StoreItemLabel . '.');
    } elseif ($_RequisitionForm['requestdate'] === '') {
        $_Message = storekeeper_flash_html('error', 'Request date is required.');
    } elseif ($_RequisitionForm['quantity'] === '' || !is_numeric($_RequisitionForm['quantity']) || (float)$_RequisitionForm['quantity'] <= 0) {
        $_Message = storekeeper_flash_html('error', 'Quantity must be a valid number greater than zero.');
    } elseif (!in_array($_RequisitionForm['dayname'], $_ValidDays, true)) {
        $_Message = storekeeper_flash_html('error', 'Please choose a valid day for the requisition.');
    } elseif (!in_array($_RequisitionForm['mealtime'], $_ValidMeals, true)) {
        $_Message = storekeeper_flash_html('error', 'Please choose a valid meal period.');
    } elseif ($_RequisitionForm['purpose'] === '') {
        $_Message = storekeeper_flash_html('error', 'Purpose of requisition is required.');
    } else {
        include("code.php");
        $_RequisitionIdEsc = mysqli_real_escape_string($con, trim((string)$code));
        $_StoreItemEsc = mysqli_real_escape_string($con, $_RequisitionForm['storeitemid']);
        $_RequestDateEsc = mysqli_real_escape_string($con, $_RequisitionForm['requestdate']);
        $_NeedBySql = $_RequisitionForm['needbydate'] !== '' ? "'" . mysqli_real_escape_string($con, $_RequisitionForm['needbydate']) . "'" : "NULL";
        $_WeekStartEsc = mysqli_real_escape_string($con, $_RequisitionForm['weekstartdate']);
        $_DayNameEsc = mysqli_real_escape_string($con, $_RequisitionForm['dayname']);
        $_MealTimeEsc = mysqli_real_escape_string($con, $_RequisitionForm['mealtime']);
        $_QuantityValue = number_format((float)$_RequisitionForm['quantity'], 2, '.', '');
        $_PurposeEsc = mysqli_real_escape_string($con, $_RequisitionForm['purpose']);
        $_NotesEsc = mysqli_real_escape_string($con, $_RequisitionForm['notes']);
        $_RequestedByEsc = mysqli_real_escape_string($con, $_UserId);

        $_SQL = @mysqli_query($con, "INSERT INTO tblmatronrequisition
            (requisitionid,storeitemid,requestdate,needbydate,weekstartdate,dayname,mealtime,quantity,purpose,notes,status,decisionnote,requestedby,decisionby,decisiondatetime,fulfilledissueid,datetimeentry)
            VALUES
            ('$_RequisitionIdEsc','$_StoreItemEsc','$_RequestDateEsc',$_NeedBySql,'$_WeekStartEsc','$_DayNameEsc','$_MealTimeEsc','$_QuantityValue','$_PurposeEsc','$_NotesEsc','pending','', '$_RequestedByEsc',NULL,NULL,NULL,NOW())");
        if ($_SQL) {
            $_SESSION['Message'] = storekeeper_flash_html('success', 'Store requisition saved successfully.');
            header("location:matron-dashboard.php?menu_week=" . urlencode($_RequisitionForm['weekstartdate']) . "#requisition-register");
            exit();
        }
        $_Message = storekeeper_flash_html('error', 'Failed to save requisition: ' . matron_esc(mysqli_error($con)));
    }
}

if (isset($_POST['cancel_requisition']) && matron_can_create_requisition($con)) {
    $_RequisitionId = trim((string)(isset($_POST['requisitionid']) ? $_POST['requisitionid'] : ''));
    if ($_RequisitionId !== '') {
        $_RequisitionIdEsc = mysqli_real_escape_string($con, $_RequisitionId);
        $_RequestedByEsc = mysqli_real_escape_string($con, $_UserId);
        $_CanCancelSql = matron_is_admin() ? "1=1" : "requestedby='$_RequestedByEsc'";
        @mysqli_query($con, "UPDATE tblmatronrequisition
            SET status='cancelled',
                decisionby='$_RequestedByEsc',
                decisiondatetime=NOW()
            WHERE requisitionid='$_RequisitionIdEsc'
              AND status='pending'
              AND $_CanCancelSql
            LIMIT 1");
        $_SESSION['Message'] = mysqli_affected_rows($con) > 0
            ? storekeeper_flash_html('warning', 'Requisition cancelled successfully.')
            : storekeeper_flash_html('error', 'That requisition could not be cancelled.');
    }
    header("location:matron-dashboard.php?menu_week=" . urlencode($_MenuWeekStart) . "#requisition-register");
    exit();
}

if (isset($_POST['save_menu_slot']) && matron_can_manage_weekly_menu($con)) {
    $_MenuForm['weekstartdate'] = matron_week_start_date(isset($_POST['weekstartdate']) ? (string)$_POST['weekstartdate'] : $_MenuWeekStart);
    $_MenuForm['dayname'] = matron_normalize_day_name(isset($_POST['dayname']) ? (string)$_POST['dayname'] : 'Monday', 'Monday');
    $_MenuForm['mealtime'] = matron_normalize_meal_name(isset($_POST['mealtime']) ? (string)$_POST['mealtime'] : 'Breakfast', 'Breakfast');
    $_MenuForm['audience'] = matron_normalize_menu_audience(isset($_POST['audience']) ? (string)$_POST['audience'] : 'student', 'student');
    $_MenuForm['menutitle'] = trim((string)(isset($_POST['menutitle']) ? $_POST['menutitle'] : ''));
    $_MenuForm['menudetails'] = trim((string)(isset($_POST['menudetails']) ? $_POST['menudetails'] : ''));
    $_MenuForm['notes'] = trim((string)(isset($_POST['notes']) ? $_POST['notes'] : ''));

    $_ValidDays = matron_menu_day_options();
    $_ValidMeals = matron_meal_options();

    if (!in_array($_MenuForm['dayname'], $_ValidDays, true)) {
        $_Message = storekeeper_flash_html('error', 'Please choose a valid day for the weekly menu.');
    } elseif (!in_array($_MenuForm['mealtime'], $_ValidMeals, true)) {
        $_Message = storekeeper_flash_html('error', 'Please choose a valid meal slot.');
    } elseif ($_MenuForm['menutitle'] === '' && $_MenuForm['menudetails'] === '') {
        $_Message = storekeeper_flash_html('error', 'Enter the menu title or menu details for this slot.');
    } else {
        $_WeekStartEsc = mysqli_real_escape_string($con, $_MenuForm['weekstartdate']);
        $_DayNameEsc = mysqli_real_escape_string($con, $_MenuForm['dayname']);
        $_MealTimeEsc = mysqli_real_escape_string($con, $_MenuForm['mealtime']);
        $_AudienceEsc = mysqli_real_escape_string($con, $_MenuForm['audience']);
        $_TitleEsc = mysqli_real_escape_string($con, $_MenuForm['menutitle']);
        $_DetailsEsc = mysqli_real_escape_string($con, $_MenuForm['menudetails']);
        $_NotesEsc = mysqli_real_escape_string($con, $_MenuForm['notes']);
        $_RecordedByEsc = mysqli_real_escape_string($con, $_UserId);
        $_ExistingRes = @mysqli_query($con, "SELECT menuid
            FROM tblmatronweeklymenu
            WHERE weekstartdate='$_WeekStartEsc'
              AND dayname='$_DayNameEsc'
              AND mealtime='$_MealTimeEsc'
              AND audience='$_AudienceEsc'
            LIMIT 1");
        if ($_ExistingRes && ($_ExistingRow = mysqli_fetch_array($_ExistingRes, MYSQLI_ASSOC))) {
            $_MenuIdEsc = mysqli_real_escape_string($con, (string)$_ExistingRow['menuid']);
            $_SQL = @mysqli_query($con, "UPDATE tblmatronweeklymenu
                SET menutitle='$_TitleEsc',
                    menudetails='$_DetailsEsc',
                    notes='$_NotesEsc',
                    status='active',
                    recordedby='$_RecordedByEsc'
                WHERE menuid='$_MenuIdEsc'
                LIMIT 1");
        } else {
            include("code.php");
            $_MenuIdEsc = mysqli_real_escape_string($con, trim((string)$code));
            $_SQL = @mysqli_query($con, "INSERT INTO tblmatronweeklymenu
                (menuid,weekstartdate,dayname,mealtime,menutitle,menudetails,notes,audience,status,datetimeentry,recordedby)
                VALUES
                ('$_MenuIdEsc','$_WeekStartEsc','$_DayNameEsc','$_MealTimeEsc','$_TitleEsc','$_DetailsEsc','$_NotesEsc','$_AudienceEsc','active',NOW(),'$_RecordedByEsc')");
        }

        if ($_SQL) {
            $_SESSION['Message'] = storekeeper_flash_html('success', matron_menu_audience_label($_MenuForm['audience']) . ' menu slot saved successfully.');
            header("location:matron-dashboard.php?menu_week=" . urlencode($_MenuForm['weekstartdate']) . "#weekly-menu-board");
            exit();
        }
        $_Message = storekeeper_flash_html('error', 'Failed to save menu slot: ' . matron_esc(mysqli_error($con)));
    }
}

if (isset($_POST['remove_menu_slot']) && matron_can_manage_weekly_menu($con)) {
    $_MenuId = trim((string)(isset($_POST['menuid']) ? $_POST['menuid'] : ''));
    $_WeekRedirect = isset($_POST['weekstartdate']) ? matron_week_start_date((string)$_POST['weekstartdate']) : $_MenuWeekStart;
    if ($_MenuId !== '') {
        $_MenuIdEsc = mysqli_real_escape_string($con, $_MenuId);
        @mysqli_query($con, "UPDATE tblmatronweeklymenu
            SET status='void'
            WHERE menuid='$_MenuIdEsc'
            LIMIT 1");
        $_SESSION['Message'] = mysqli_affected_rows($con) > 0
            ? storekeeper_flash_html('warning', 'Menu slot removed from the selected week.')
            : storekeeper_flash_html('error', 'That menu slot could not be removed.');
    }
    header("location:matron-dashboard.php?menu_week=" . urlencode($_WeekRedirect) . "#weekly-menu-board");
    exit();
}

$_Summary = matron_dashboard_summary($con);
$_RequisitionSummary = matron_requisition_summary($con);
$_CurrentStudentMenu = matron_current_week_menu_context($con, $_CurrentWeekStart, 'student');
$_CurrentTeacherMenu = matron_current_week_menu_context($con, $_CurrentWeekStart, 'teacher');
$_SelectedStudentWeekRows = matron_fetch_weekly_menu_rows($con, array(
    'weekstartdate' => $_MenuWeekStart,
    'status' => 'active',
    'audience' => 'student',
    'fallback_to_all' => true,
    'limit' => 40
));
$_SelectedTeacherWeekRows = matron_fetch_weekly_menu_rows($con, array(
    'weekstartdate' => $_MenuWeekStart,
    'status' => 'active',
    'audience' => 'teacher',
    'fallback_to_all' => true,
    'limit' => 40
));
$_SelectedStudentWeekGrouped = matron_group_menu_rows($_SelectedStudentWeekRows);
$_SelectedTeacherWeekGrouped = matron_group_menu_rows($_SelectedTeacherWeekRows);
$_SelectedMenuSummary = matron_weekly_menu_summary($con, $_MenuWeekStart);
$_RecentRequisitionRows = matron_recent_requisitions($con, 60);
$_FoodWatchRows = matron_food_watch_rows($con, 8);
$_RecentFoodIssues = matron_recent_food_issues($con, 8);
$_FoodItems = isset($_FoodCatalog['rows']) && is_array($_FoodCatalog['rows']) ? $_FoodCatalog['rows'] : array();
$_SelectedStoreItemName = 'Selected item';
$_SelectedStoreItemPickerLabel = '';
foreach ($_FoodItems as $_CatalogItem) {
    if ($_RequisitionForm['storeitemid'] !== '' && $_RequisitionForm['storeitemid'] === (string)$_CatalogItem['storeitemid']) {
        $_SelectedStoreItemName = trim((string)$_CatalogItem['itemname']) !== '' ? trim((string)$_CatalogItem['itemname']) : 'Selected item';
        $_SelectedStoreItemPickerLabel = storekeeper_item_picker_label($_CatalogItem);
        break;
    }
}
if ($_SelectedStoreItemName === 'Selected item' && $_RequisitionForm['storeitemid'] !== '') {
    $_SelectedStoreItemRow = storekeeper_get_item_row($con, $_RequisitionForm['storeitemid']);
    if ($_SelectedStoreItemRow && trim((string)$_SelectedStoreItemRow['itemname']) !== '') {
        $_SelectedStoreItemName = trim((string)$_SelectedStoreItemRow['itemname']);
        $_SelectedStoreItemPickerLabel = storekeeper_item_picker_label($_SelectedStoreItemRow);
    }
}
if ($_SelectedStoreItemPickerLabel === '' && $_RequisitionForm['storeitemlabel'] !== '') {
    $_SelectedStoreItemPickerLabel = $_RequisitionForm['storeitemlabel'];
}
$_SelectedRequestSummary = $_SelectedStoreItemName . ' for ' . matron_requisition_slot_label($_RequisitionForm['dayname'], $_RequisitionForm['mealtime']);
$_PopulationLabel = (int)$_Summary['boarding_students_total'] > 0
    ? number_format((int)$_Summary['boarding_students_total']) . ' boarders'
    : 'resident students';
$_PurposeSuggestions = array(
    'Breakfast service for ' . $_PopulationLabel,
    'Lunch service for ' . $_PopulationLabel,
    'Supper service for ' . $_PopulationLabel,
    'Kitchen cleaning and sanitation supplies',
    'Weekend pantry and cold room top-up',
    'Special diet or infirmary meal support'
);
$_CurrentStudentMenuCount = isset($_CurrentStudentMenu['rows']) && is_array($_CurrentStudentMenu['rows']) ? count($_CurrentStudentMenu['rows']) : 0;
$_CurrentTeacherMenuCount = isset($_CurrentTeacherMenu['rows']) && is_array($_CurrentTeacherMenu['rows']) ? count($_CurrentTeacherMenu['rows']) : 0;
$_SelectedStudentWeekCount = is_array($_SelectedStudentWeekRows) ? count($_SelectedStudentWeekRows) : 0;
$_SelectedTeacherWeekCount = is_array($_SelectedTeacherWeekRows) ? count($_SelectedTeacherWeekRows) : 0;
$_RecentRequisitionCount = is_array($_RecentRequisitionRows) ? count($_RecentRequisitionRows) : 0;
$_FoodWatchCount = is_array($_FoodWatchRows) ? count($_FoodWatchRows) : 0;
$_RecentFoodIssueCount = is_array($_RecentFoodIssues) ? count($_RecentFoodIssues) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/storekeeper.css">
<link rel="stylesheet" href="css/matron.css">
</head>
<body class="storekeeper-page matron-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<main class="sk-main">
    <div class="sk-shell">
        <section class="sk-hero">
            <div>
                <span class="sk-kicker"><i class="fa fa-cutlery"></i> Matron Workspace</span>
                <h1>Plan meals and send requests to the school store.</h1>
                <p>Use this page to prepare the weekly menu, request food and kitchen items from the store, and keep the menu visible on the student and teacher dashboards.</p>
                <div class="sk-link-grid">
                    <a class="sk-link-chip" href="#requisition-form"><i class="fa fa-shopping-basket"></i> New Request</a>
                    <a class="sk-link-chip" href="#menu-form"><i class="fa fa-calendar"></i> Plan Weekly Menu</a>
                    <a class="sk-link-chip" href="#weekly-menu-board"><i class="fa fa-cutlery"></i> Weekly Menu Board</a>
                    <a class="sk-link-chip" href="#requisition-register"><i class="fa fa-list"></i> Request Register</a>
                    <a class="sk-link-chip" href="#food-watch"><i class="fa fa-balance-scale"></i> Stock Watch</a>
                </div>
                <div class="sk-hero__chips" style="margin-top:16px;">
                    <span class="sk-chip"><i class="fa fa-clock-o"></i> Waiting at Store: <?php echo number_format((int)$_Summary['requisition_pending']); ?></span>
                    <span class="sk-chip"><i class="fa fa-user-circle-o"></i> Waiting for Head: <?php echo number_format((int)$_Summary['requisition_waiting_headmaster']); ?></span>
                    <span class="sk-chip"><i class="fa fa-calendar-check-o"></i> Current Week Menu Slots: <?php echo number_format((int)$_Summary['menu_slot_filled']); ?>/<?php echo number_format((int)$_Summary['menu_slot_total']); ?></span>
                    <span class="sk-chip"><i class="fa fa-exclamation-triangle"></i> <?php echo $_FoodCatalogUsesFallback ? 'Store Lines Low' : 'Food Lines Low'; ?>: <?php echo number_format((int)$_Summary['food_low_stock']); ?></span>
                    <span class="sk-chip"><i class="fa fa-home"></i> Boarders: <?php echo number_format((int)$_Summary['boarding_students_total']); ?></span>
                </div>
            </div>
            <div class="sk-stats">
                <article class="sk-stat">
                    <span>Waiting at Store</span>
                    <strong><?php echo number_format((int)$_Summary['requisition_pending']); ?></strong>
                    <small>Requests sent to the store and still waiting for review.</small>
                </article>
                <article class="sk-stat">
                    <span>Waiting for Head</span>
                    <strong><?php echo number_format((int)$_Summary['requisition_waiting_headmaster']); ?></strong>
                    <small>Requests the store has checked and moved on for final approval.</small>
                </article>
                <article class="sk-stat">
                    <span>Menu Slots Filled</span>
                    <strong><?php echo number_format((int)$_Summary['menu_slot_filled']); ?></strong>
                    <small><?php echo matron_esc($_CurrentStudentMenu['week_label']); ?> now covers both the student and teacher dashboard menus.</small>
                </article>
                <article class="sk-stat">
                    <span>Boarders to feed</span>
                    <strong><?php echo number_format((int)$_Summary['boarding_students_total']); ?></strong>
                    <small>Current boarding population the kitchen is mainly serving.</small>
                </article>
            </div>
        </section>

        <?php if ($_Message !== "") { ?>
        <?php echo $_Message; ?>
        <?php } ?>

        <section class="sk-summary-grid">
            <article class="sk-summary-card">
                <span>All Students</span>
                <strong><?php echo number_format((int)$_Summary['student_total']); ?></strong>
                <small>Active students.</small>
                <?php echo dashboard_student_batch_breakdown_html($_Summary, 'student_total', 'Batches', 'No batch yet.'); ?>
            </article>
            <article class="sk-summary-card">
                <span>Day Students</span>
                <strong><?php echo number_format((int)$_Summary['day_students_total']); ?></strong>
                <small>Day students.</small>
                <?php echo dashboard_student_batch_breakdown_html($_Summary, 'day_students_total', 'Batches', 'No batch yet.'); ?>
            </article>
            <article class="sk-summary-card">
                <span>Boarders</span>
                <strong><?php echo number_format((int)$_Summary['boarding_students_total']); ?></strong>
                <small>Boarders.</small>
                <?php echo dashboard_student_batch_breakdown_html($_Summary, 'boarding_students_total', 'Batches', 'No batch yet.'); ?>
            </article>
            <article class="sk-summary-card">
                <span>Boarder Boys</span>
                <strong><?php echo number_format((int)$_Summary['boarding_boys']); ?></strong>
                <small>Boarding boys.</small>
                <?php echo dashboard_student_batch_breakdown_html($_Summary, 'boarding_boys', 'Batches', 'No batch yet.'); ?>
            </article>
            <article class="sk-summary-card">
                <span>Boarder Girls</span>
                <strong><?php echo number_format((int)$_Summary['boarding_girls']); ?></strong>
                <small>Boarding girls.</small>
                <?php echo dashboard_student_batch_breakdown_html($_Summary, 'boarding_girls', 'Batches', 'No batch yet.'); ?>
            </article>
            <article class="sk-summary-card">
                <span>Waiting for Head</span>
                <strong><?php echo number_format((int)$_Summary['requisition_waiting_headmaster']); ?></strong>
                <small>Requests already checked by the store and waiting for the headmaster.</small>
            </article>
            <article class="sk-summary-card">
                <span>Final Approved</span>
                <strong><?php echo number_format((int)$_Summary['requisition_approved']); ?></strong>
                <small>Requests fully approved and now waiting to be issued.</small>
            </article>
            <article class="sk-summary-card">
                <span>Issued Requisitions</span>
                <strong><?php echo number_format((int)$_Summary['requisition_issued']); ?></strong>
                <small>Requests already supplied from the store.</small>
            </article>
            <article class="sk-summary-card">
                <span>Open Menu Slots</span>
                <strong><?php echo number_format((int)$_SelectedMenuSummary['slot_open']); ?></strong>
                <small><?php echo matron_esc(matron_week_label($_SelectedMenuSummary['week_start'])); ?> still has student or teacher meal slots to fill.</small>
            </article>
        </section>

        <div class="sk-layout">
            <section class="sk-panel" id="requisition-form">
                <div class="sk-panel__header">
                    <div>
                        <h2>New Store Request</h2>
                        <p><?php echo $_FoodCatalogUsesFallback ? 'Request active store items while the store categories are still being arranged.' : 'Request food or kitchen items from the store for a meal or service day.'; ?></p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <?php if ($_FoodCatalogUsesFallback && $_FoodCatalogMessage !== '') { ?>
                    <div class="matron-tip matron-tip--warning">
                        <i class="fa fa-info-circle"></i>
                        <span><?php echo matron_esc($_FoodCatalogMessage); ?> You can still use the request form with these active items.</span>
                    </div>
                    <?php } ?>
                    <?php if (empty($_FoodItems)) { ?>
                    <div class="sk-empty"><?php echo matron_esc($_FoodCatalogMessage !== '' ? $_FoodCatalogMessage : 'No active food or kitchen item has been added in the store yet.'); ?></div>
                    <?php } else { ?>
                    <form method="post" class="sk-form" action="matron-dashboard.php?menu_week=<?php echo urlencode($_MenuWeekStart); ?>#requisition-form">
                        <div class="sk-form-grid">
                            <div class="sk-field">
                                <label for="requestdate">Request Date</label>
                                <input type="date" id="requestdate" name="requestdate" value="<?php echo matron_esc($_RequisitionForm['requestdate']); ?>" required>
                            </div>
                            <div class="sk-field">
                                <label for="needbydate">Needed By</label>
                                <input type="date" id="needbydate" name="needbydate" value="<?php echo matron_esc($_RequisitionForm['needbydate']); ?>">
                            </div>
                            <div class="sk-field">
                                <label for="weekstartdate">Menu Week</label>
                                <input type="date" id="weekstartdate" name="weekstartdate" value="<?php echo matron_esc($_RequisitionForm['weekstartdate']); ?>" required>
                            </div>
                            <div class="sk-field">
                                <label for="quantity">Quantity</label>
                                <input type="number" id="quantity" name="quantity" value="<?php echo matron_esc($_RequisitionForm['quantity']); ?>" min="0.01" step="0.01" required>
                            </div>
                            <div class="sk-field">
                                <label for="dayname">Day</label>
                                <input type="text" id="dayname" name="dayname" value="<?php echo matron_esc($_RequisitionForm['dayname']); ?>" list="matron-day-list" placeholder="Select day" autocomplete="off" oninput="if (window.matronSyncRequisitionFields) { window.matronSyncRequisitionFields(true); }" required>
                                <datalist id="matron-day-list">
                                    <?php foreach (matron_menu_day_options() as $_DayName) { ?>
                                    <option value="<?php echo matron_esc($_DayName); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field">
                                <label for="mealtime">Meal Time</label>
                                <input type="text" id="mealtime" name="mealtime" value="<?php echo matron_esc($_RequisitionForm['mealtime']); ?>" list="matron-meal-list" placeholder="Select meal time" autocomplete="off" oninput="if (window.matronSyncRequisitionFields) { window.matronSyncRequisitionFields(true); }" required>
                                <datalist id="matron-meal-list">
                                    <?php foreach (matron_meal_options() as $_MealName) { ?>
                                    <option value="<?php echo matron_esc($_MealName); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="storeitempicker">Store Item</label>
                                <input type="hidden" id="storeitemid" name="storeitemid" value="<?php echo matron_esc($_RequisitionForm['storeitemid']); ?>">
                                <input type="text" id="storeitempicker" name="storeitemlabel" value="<?php echo matron_esc($_SelectedStoreItemPickerLabel); ?>" list="matron-storeitem-list" placeholder="<?php echo $_FoodCatalogUsesFallback ? 'Select active store item' : 'Select food or kitchen item'; ?>" autocomplete="off" oninput="if (window.matronSyncRequisitionFields) { window.matronSyncRequisitionFields(true); }" required>
                                <datalist id="matron-storeitem-list">
                                    <?php foreach ($_FoodItems as $_Item) { ?>
                                    <option value="<?php echo matron_esc(storekeeper_item_picker_label($_Item)); ?>" data-itemid="<?php echo matron_esc($_Item['storeitemid']); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="requestsummarydisplay">Selected Request</label>
                                <div id="requestsummarydisplay" class="matron-readonly-field"><?php echo matron_esc($_SelectedRequestSummary); ?></div>
                                <small class="matron-field-help">This shows the item and meal slot currently selected for the request.</small>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="purpose">Purpose</label>
                                <input type="hidden" id="purposemode" name="purposemode" value="<?php echo matron_esc($_RequisitionForm['purposemode']); ?>">
                                <input type="text" id="purpose" name="purpose" value="<?php echo matron_esc($_RequisitionForm['purpose']); ?>" list="matron-purpose-list" placeholder="e.g. Rice for lunch service, Breakfast stock top-up, Kitchen cleaning" oninput="if (window.matronHandlePurposeInput) { window.matronHandlePurposeInput(); }" required>
                                <datalist id="matron-purpose-list">
                                    <?php foreach ($_PurposeSuggestions as $_PurposeSuggestion) { ?>
                                    <option value="<?php echo matron_esc($_PurposeSuggestion); ?>">
                                    <?php } ?>
                                </datalist>
                                <div class="matron-quick-fill" data-population-label="<?php echo matron_esc($_PopulationLabel); ?>">
                                    <button type="button" class="matron-preset" data-purpose-mode="meal_slot" data-purpose-template="{item} for {meal} service for {population} on {day}">Use Meal Slot</button>
                                    <button type="button" class="matron-preset" data-purpose-mode="kitchen_cleaning" data-purpose="Kitchen cleaning and sanitation supplies">Kitchen Cleaning</button>
                                    <button type="button" class="matron-preset" data-purpose-mode="special_diet" data-purpose="Special diet or infirmary meal support">Special Diet</button>
                                    <button type="button" class="matron-preset" data-purpose-mode="emergency_top_up" data-purpose-template="Emergency stock top-up of {item} for {meal} service on {day}">Emergency Top-Up</button>
                                </div>
                                <div class="matron-selection-preview" id="matron-purpose-preview" aria-live="polite"></div>
                                <small class="matron-field-help">The purpose line follows the selected meal until you type your own wording.</small>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="requisition_notes">Notes</label>
                                <textarea id="requisition_notes" name="notes" placeholder="Optional note about urgency, cooking plan, or any special instruction."><?php echo matron_esc($_RequisitionForm['notes']); ?></textarea>
                            </div>
                        </div>
                        <div class="sk-actions">
                            <button type="submit" name="save_requisition" class="sk-button"><i class="fa fa-send"></i> Send request</button>
                        </div>
                    </form>
                    <div class="matron-helper-card">
                        <div class="matron-helper-card__header">
                            <h3>Kitchen Snapshot</h3>
                            <p>Key feeding figures to keep in view while you prepare requests.</p>
                        </div>
                        <div class="matron-helper-grid">
                            <div class="matron-helper-stat">
                                <span>Boarders</span>
                                <strong><?php echo number_format((int)$_Summary['boarding_students_total']); ?></strong>
                            </div>
                            <div class="matron-helper-stat">
                                <span>Open Slots</span>
                                <strong><?php echo number_format((int)$_SelectedMenuSummary['slot_open']); ?></strong>
                            </div>
                            <div class="matron-helper-stat">
                                <span>Waiting at Store</span>
                                <strong><?php echo number_format((int)$_Summary['requisition_pending']); ?></strong>
                            </div>
                            <div class="matron-helper-stat">
                                <span>Waiting for Head</span>
                                <strong><?php echo number_format((int)$_Summary['requisition_waiting_headmaster']); ?></strong>
                            </div>
                        </div>
                        <div class="matron-helper-notes">
                            <span><i class="fa fa-home"></i> Boarders without house: <?php echo number_format((int)$_Summary['boarders_without_house']); ?></span>
                            <span><i class="fa fa-book"></i> Student items overdue for return: <?php echo number_format((int)$_Summary['boarding_student_items_overdue']); ?></span>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </section>

            <section class="sk-panel" id="menu-form">
                <div class="sk-panel__header">
                    <div>
                        <h2>Weekly Menu Planner</h2>
                        <p>Save one meal slot at a time for either students or teachers. If that week, day, meal, and audience already exist, saving again updates it.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <form method="post" class="sk-form" action="matron-dashboard.php?menu_week=<?php echo urlencode($_MenuWeekStart); ?>#menu-form">
                        <div class="sk-form-grid">
                            <div class="sk-field">
                                <label for="menu_weekstartdate">Week Start</label>
                                <input type="date" id="menu_weekstartdate" name="weekstartdate" value="<?php echo matron_esc($_MenuForm['weekstartdate']); ?>" required>
                            </div>
                            <div class="sk-field">
                                <label for="menu_dayname">Day</label>
                                <input type="text" id="menu_dayname" name="dayname" value="<?php echo matron_esc($_MenuForm['dayname']); ?>" list="menu-day-list" placeholder="Select day" autocomplete="off" required>
                                <datalist id="menu-day-list">
                                    <?php foreach (matron_menu_day_options() as $_DayName) { ?>
                                    <option value="<?php echo matron_esc($_DayName); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field">
                                <label for="menu_mealtime">Meal Time</label>
                                <input type="text" id="menu_mealtime" name="mealtime" value="<?php echo matron_esc($_MenuForm['mealtime']); ?>" list="menu-meal-list" placeholder="Select meal time" autocomplete="off" required>
                                <datalist id="menu-meal-list">
                                    <?php foreach (matron_meal_options() as $_MealName) { ?>
                                    <option value="<?php echo matron_esc($_MealName); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field">
                                <label for="menu_audience">Audience</label>
                                <select id="menu_audience" name="audience" required>
                                    <?php foreach ($_MenuAudienceOptions as $_AudienceValue => $_AudienceLabel) { ?>
                                    <option value="<?php echo matron_esc($_AudienceValue); ?>" <?php echo $_MenuForm['audience'] === $_AudienceValue ? 'selected' : ''; ?>><?php echo matron_esc($_AudienceLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="menutitle">Menu Title</label>
                                <input type="text" id="menutitle" name="menutitle" value="<?php echo matron_esc($_MenuForm['menutitle']); ?>" placeholder="e.g. Rice and stew, Porridge, Banku with okro soup">
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="menudetails">Menu Details</label>
                                <textarea id="menudetails" name="menudetails" placeholder="List the food items or serving note for this meal."><?php echo matron_esc($_MenuForm['menudetails']); ?></textarea>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="menu_notes">Notes</label>
                                <textarea id="menu_notes" name="notes" placeholder="Optional note for preparation, substitution, or any special arrangement."><?php echo matron_esc($_MenuForm['notes']); ?></textarea>
                            </div>
                        </div>
                        <div class="sk-actions">
                            <button type="submit" name="save_menu_slot" class="sk-button"><i class="fa fa-save"></i> Save menu slot</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <details class="sk-panel sk-disclosure">
            <summary class="sk-disclosure__summary">
                <span class="sk-disclosure__eyebrow">Live Menu</span>
                <strong>Menu showing on the dashboards</strong>
                <small>Students: <?php echo number_format((int)$_CurrentStudentMenuCount); ?> slots. Teachers: <?php echo number_format((int)$_CurrentTeacherMenuCount); ?> slots.</small>
            </summary>
            <div class="sk-panel__body">
                <div class="sk-layout">
                    <div class="sk-panel">
                        <div class="sk-panel__header">
                            <div>
                                <h2>Student dashboard menu</h2>
                                <p>This is what students currently see on their dashboard.</p>
                            </div>
                        </div>
                        <div class="sk-panel__body">
                            <?php if (empty($_CurrentStudentMenu['rows'])) { ?>
                            <div class="sk-empty">No student menu has been added for the current week yet.</div>
                            <?php } else { ?>
                            <div class="matron-menu-board">
                                <?php foreach ($_CurrentStudentMenu['grouped'] as $_DayName => $_Meals) { ?>
                                <article class="matron-menu-day">
                                    <h3><?php echo matron_esc($_DayName); ?></h3>
                                    <div class="matron-menu-day__meals">
                                        <?php foreach ($_Meals as $_MealName => $_MealRow) { ?>
                                        <div class="matron-menu-meal">
                                            <span class="matron-menu-meal__label"><?php echo matron_esc($_MealName); ?></span>
                                            <strong><?php echo $_MealRow ? matron_esc(matron_menu_display_text($_MealRow)) : 'Not added'; ?></strong>
                                            <?php if ($_MealRow && trim((string)$_MealRow['notes']) !== '') { ?>
                                            <small><?php echo matron_esc($_MealRow['notes']); ?></small>
                                            <?php } ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </article>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="sk-panel">
                        <div class="sk-panel__header">
                            <div>
                                <h2>Teacher dashboard menu</h2>
                                <p>This is what teachers currently see on their dashboard.</p>
                            </div>
                        </div>
                        <div class="sk-panel__body">
                            <?php if (empty($_CurrentTeacherMenu['rows'])) { ?>
                            <div class="sk-empty">No teacher menu has been added for the current week yet.</div>
                            <?php } else { ?>
                            <div class="matron-menu-board">
                                <?php foreach ($_CurrentTeacherMenu['grouped'] as $_DayName => $_Meals) { ?>
                                <article class="matron-menu-day">
                                    <h3><?php echo matron_esc($_DayName); ?></h3>
                                    <div class="matron-menu-day__meals">
                                        <?php foreach ($_Meals as $_MealName => $_MealRow) { ?>
                                        <div class="matron-menu-meal">
                                            <span class="matron-menu-meal__label"><?php echo matron_esc($_MealName); ?></span>
                                            <strong><?php echo $_MealRow ? matron_esc(matron_menu_display_text($_MealRow)) : 'Not added'; ?></strong>
                                            <?php if ($_MealRow && trim((string)$_MealRow['notes']) !== '') { ?>
                                            <small><?php echo matron_esc($_MealRow['notes']); ?></small>
                                            <?php } ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </article>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </details>

        <details class="sk-panel sk-disclosure" id="weekly-menu-board">
            <summary class="sk-disclosure__summary">
                <span class="sk-disclosure__eyebrow">Week Board</span>
                <strong>Selected week menu board</strong>
                <small><?php echo matron_esc(matron_week_label($_MenuWeekStart)); ?>. Students: <?php echo number_format((int)$_SelectedStudentWeekCount); ?> slots. Teachers: <?php echo number_format((int)$_SelectedTeacherWeekCount); ?> slots.</small>
            </summary>
            <div class="sk-panel__body">
                <div class="sk-panel__header" style="padding:0 0 16px;">
                    <div>
                        <h2>Selected week menu board</h2>
                        <p><?php echo matron_esc(matron_week_label($_MenuWeekStart)); ?> currently has <?php echo number_format((int)$_SelectedMenuSummary['slot_filled']); ?> filled slots and <?php echo number_format((int)$_SelectedMenuSummary['slot_open']); ?> open slots across the student and teacher meal plans.</p>
                    </div>
                    <form method="get" action="matron-dashboard.php" class="sk-actions">
                        <div class="sk-field" style="min-width:220px;">
                            <label for="menu_week">Week Start</label>
                            <input type="date" id="menu_week" name="menu_week" value="<?php echo matron_esc($_MenuWeekStart); ?>">
                        </div>
                        <div class="sk-actions" style="margin-top:28px;">
                            <button type="submit" class="sk-button"><i class="fa fa-filter"></i> Load selected week</button>
                        </div>
                    </form>
                </div>
                <div class="sk-layout">
                    <div class="sk-panel">
                        <div class="sk-panel__header">
                            <div>
                                <h2>Student meal plan</h2>
                                <p><?php echo matron_esc(matron_week_label($_MenuWeekStart)); ?> for students.</p>
                            </div>
                        </div>
                        <div class="sk-panel__body">
                            <div class="matron-menu-board">
                                <?php foreach ($_SelectedStudentWeekGrouped as $_DayName => $_Meals) { ?>
                                <article class="matron-menu-day">
                                    <h3><?php echo matron_esc($_DayName); ?></h3>
                                    <div class="matron-menu-day__meals">
                                        <?php foreach ($_Meals as $_MealName => $_MealRow) { ?>
                                        <div class="matron-menu-meal">
                                            <span class="matron-menu-meal__label"><?php echo matron_esc($_MealName); ?></span>
                                            <strong><?php echo $_MealRow ? matron_esc(matron_menu_display_text($_MealRow)) : 'Not added'; ?></strong>
                                            <?php if ($_MealRow && trim((string)$_MealRow['notes']) !== '') { ?>
                                            <small><?php echo matron_esc($_MealRow['notes']); ?></small>
                                            <?php } ?>
                                            <?php if ($_MealRow) { ?>
                                            <form method="post" action="matron-dashboard.php?menu_week=<?php echo urlencode($_MenuWeekStart); ?>#weekly-menu-board" class="matron-inline-form">
                                                <input type="hidden" name="menuid" value="<?php echo matron_esc($_MealRow['menuid']); ?>">
                                                <input type="hidden" name="weekstartdate" value="<?php echo matron_esc($_MenuWeekStart); ?>">
                                                <button type="submit" name="remove_menu_slot" class="matron-inline-button" onclick="return confirm('Remove this student menu slot from the selected week?');"><i class="fa fa-times-circle"></i> Remove</button>
                                            </form>
                                            <?php } ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </article>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="sk-panel">
                        <div class="sk-panel__header">
                            <div>
                                <h2>Teacher meal plan</h2>
                                <p><?php echo matron_esc(matron_week_label($_MenuWeekStart)); ?> for teachers.</p>
                            </div>
                        </div>
                        <div class="sk-panel__body">
                            <div class="matron-menu-board">
                                <?php foreach ($_SelectedTeacherWeekGrouped as $_DayName => $_Meals) { ?>
                                <article class="matron-menu-day">
                                    <h3><?php echo matron_esc($_DayName); ?></h3>
                                    <div class="matron-menu-day__meals">
                                        <?php foreach ($_Meals as $_MealName => $_MealRow) { ?>
                                        <div class="matron-menu-meal">
                                            <span class="matron-menu-meal__label"><?php echo matron_esc($_MealName); ?></span>
                                            <strong><?php echo $_MealRow ? matron_esc(matron_menu_display_text($_MealRow)) : 'Not added'; ?></strong>
                                            <?php if ($_MealRow && trim((string)$_MealRow['notes']) !== '') { ?>
                                            <small><?php echo matron_esc($_MealRow['notes']); ?></small>
                                            <?php } ?>
                                            <?php if ($_MealRow) { ?>
                                            <form method="post" action="matron-dashboard.php?menu_week=<?php echo urlencode($_MenuWeekStart); ?>#weekly-menu-board" class="matron-inline-form">
                                                <input type="hidden" name="menuid" value="<?php echo matron_esc($_MealRow['menuid']); ?>">
                                                <input type="hidden" name="weekstartdate" value="<?php echo matron_esc($_MenuWeekStart); ?>">
                                                <button type="submit" name="remove_menu_slot" class="matron-inline-button" onclick="return confirm('Remove this teacher menu slot from the selected week?');"><i class="fa fa-times-circle"></i> Remove</button>
                                            </form>
                                            <?php } ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </article>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </details>

        <details class="sk-panel sk-disclosure" id="requisition-register">
            <summary class="sk-disclosure__summary">
                <span class="sk-disclosure__eyebrow">Store Requests</span>
                <strong>Request register</strong>
                <small>Total: <?php echo number_format((int)$_RequisitionSummary['total']); ?>. Waiting at store: <?php echo number_format((int)$_RequisitionSummary['pending']); ?>. Waiting for head: <?php echo number_format((int)$_RequisitionSummary['awaiting_headmaster']); ?>.</small>
            </summary>
            <div class="sk-panel__body">
                <div class="sk-panel__header" style="padding:0 0 16px;">
                    <div>
                        <h2>Request register</h2>
                        <p>All requests sent to the store, together with the stage each one has reached.</p>
                    </div>
                </div>
                <div class="sk-summary-grid" style="margin-bottom:16px;">
                    <article class="sk-summary-card">
                        <span>Total</span>
                        <strong><?php echo number_format((int)$_RequisitionSummary['total']); ?></strong>
                        <small>All requests raised so far.</small>
                    </article>
                    <article class="sk-summary-card">
                        <span>Waiting at Store</span>
                        <strong><?php echo number_format((int)$_RequisitionSummary['pending']); ?></strong>
                        <small>Still waiting for store review.</small>
                    </article>
                    <article class="sk-summary-card">
                        <span>Waiting for Head</span>
                        <strong><?php echo number_format((int)$_RequisitionSummary['awaiting_headmaster']); ?></strong>
                        <small>The store has checked these and sent them on for final approval.</small>
                    </article>
                    <article class="sk-summary-card">
                        <span>Final Approved</span>
                        <strong><?php echo number_format((int)$_RequisitionSummary['approved']); ?></strong>
                        <small>Approved finally and now waiting to be issued.</small>
                    </article>
                    <article class="sk-summary-card">
                        <span>Issued</span>
                        <strong><?php echo number_format((int)$_RequisitionSummary['issued']); ?></strong>
                        <small>Already supplied from the store.</small>
                    </article>
                </div>

                <?php if (empty($_RecentRequisitionRows)) { ?>
                <div class="sk-empty">No request has been recorded yet.</div>
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
                                <th>Meal Slot</th>
                                <th>Purpose</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_RecentRequisitionRows as $_Row) { ?>
                            <tr>
                                <td>
                                    <?php if ((string)$_Row['status'] === 'pending') { ?>
                                    <form method="post" action="matron-dashboard.php?menu_week=<?php echo urlencode($_MenuWeekStart); ?>#requisition-register" class="matron-inline-form">
                                        <input type="hidden" name="requisitionid" value="<?php echo matron_esc($_Row['requisitionid']); ?>">
                                        <button type="submit" name="cancel_requisition" class="matron-inline-button" onclick="return confirm('Cancel this requisition?');"><i class="fa fa-ban"></i> Cancel</button>
                                    </form>
                                    <?php } else { ?>
                                    <span class="sk-muted">No action</span>
                                    <?php } ?>
                                </td>
                                <td><?php echo matron_dashboard_date($_Row['requestdate']); ?></td>
                                <td>
                                    <?php echo matron_esc($_Row['itemname']); ?>
                                    <small><?php echo matron_esc($_Row['requested_by_name']); ?></small>
                                </td>
                                <td><?php echo storekeeper_format_quantity($_Row['quantity']); ?> <?php echo matron_esc($_Row['unitname']); ?></td>
                                <td><?php echo matron_dashboard_date($_Row['needbydate']); ?></td>
                                <td>
                                    <strong class="matron-cell-title"><?php echo matron_esc(matron_requisition_slot_label($_Row['dayname'], $_Row['mealtime'])); ?></strong>
                                    <small><?php echo matron_esc($_Row['dayname']); ?> schedule</small>
                                </td>
                                <td>
                                    <strong class="matron-cell-title"><?php echo matron_esc($_Row['purpose']); ?></strong>
                                    <?php if (trim((string)$_Row['notes']) !== '') { ?>
                                    <small><?php echo matron_esc($_Row['notes']); ?></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php echo matron_requisition_badge_html($_Row['status']); ?>
                                    <?php if (trim((string)$_Row['stage_note']) !== '') { ?>
                                    <small><?php echo matron_esc($_Row['stage_note']); ?></small>
                                    <?php } ?>
                                    <?php if (trim((string)$_Row['store_decision_by_name']) !== '') { ?>
                                    <small>Store: <?php echo matron_esc($_Row['store_decision_by_name']); ?></small>
                                    <?php } ?>
                                    <?php if (trim((string)$_Row['head_decision_by_name']) !== '') { ?>
                                    <small>Head: <?php echo matron_esc($_Row['head_decision_by_name']); ?></small>
                                    <?php } ?>
                                    <?php if (!empty($_Row['is_headmaster_adjusted'])) { ?>
                                    <small>The headmaster changed the final details before approval.</small>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </details>

        <div class="sk-layout">
            <details class="sk-panel sk-disclosure" id="food-watch">
                <summary class="sk-disclosure__summary">
                    <span class="sk-disclosure__eyebrow">Stock Watch</span>
                    <strong>Food stock watch</strong>
                    <small><?php echo number_format((int)$_FoodWatchCount); ?> line<?php echo $_FoodWatchCount === 1 ? '' : 's'; ?> need attention.</small>
                </summary>
                <div class="sk-panel__body">
                    <div class="sk-panel__header" style="padding:0 0 16px;">
                        <div>
                            <h2>Food stock watch</h2>
                            <p><?php echo $_FoodCatalogUsesFallback ? 'Showing active store items because the store categories are still general.' : 'Food lines from the store that need attention before they affect the weekly menu.'; ?></p>
                        </div>
                    </div>
                    <?php if (empty($_FoodWatchRows)) { ?>
                    <div class="sk-empty">No food or kitchen item is low right now.</div>
                    <?php } else { ?>
                    <div class="sk-list">
                        <?php foreach ($_FoodWatchRows as $_Row) { ?>
                        <div class="sk-list-item">
                            <strong><?php echo matron_esc($_Row['itemname']); ?></strong>
                            <div class="sk-inline-meta">
                                <span><i class="fa fa-folder-open-o"></i> <?php echo matron_esc($_Row['itemcategory']); ?></span>
                                <span><i class="fa fa-balance-scale"></i> Balance: <?php echo storekeeper_format_quantity($_Row['current_balance']); ?> <?php echo matron_esc($_Row['unitname']); ?></span>
                                <span><i class="fa fa-bell-o"></i> Reorder: <?php echo storekeeper_format_quantity($_Row['reorderlevel']); ?></span>
                            </div>
                            <div style="margin-top:10px;"><?php echo storekeeper_stock_badge_html($_Row['current_balance'], $_Row['reorderlevel']); ?></div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
            </details>

            <details class="sk-panel sk-disclosure" id="recent-food-issues">
                <summary class="sk-disclosure__summary">
                    <span class="sk-disclosure__eyebrow">Store Issues</span>
                    <strong>Recent food issues</strong>
                    <small><?php echo number_format((int)$_RecentFoodIssueCount); ?> recent issue record<?php echo $_RecentFoodIssueCount === 1 ? '' : 's'; ?> from the store.</small>
                </summary>
                <div class="sk-panel__body">
                    <div class="sk-panel__header" style="padding:0 0 16px;">
                        <div>
                            <h2>Recent food issues</h2>
                            <p><?php echo $_FoodCatalogUsesFallback ? 'Latest active store items already issued while the store categories are still being refined.' : 'Latest food and kitchen items already issued for kitchen use.'; ?></p>
                        </div>
                    </div>
                    <?php if (empty($_RecentFoodIssues)) { ?>
                    <div class="sk-empty">No recent food or kitchen issue was found.</div>
                    <?php } else { ?>
                    <div class="sk-table-wrap">
                        <table class="sk-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Issued To</th>
                                    <th>Purpose</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_RecentFoodIssues as $_Issue) { ?>
                                <tr>
                                    <td><?php echo matron_dashboard_date($_Issue['issuedate']); ?></td>
                                    <td>
                                        <?php echo matron_esc($_Issue['itemname']); ?>
                                        <small><?php echo matron_esc($_Issue['itemcategory']); ?></small>
                                    </td>
                                    <td><?php echo matron_esc($_Issue['issuedto']); ?></td>
                                    <td><?php echo matron_esc($_Issue['purpose']); ?></td>
                                    <td><?php echo storekeeper_format_quantity($_Issue['quantity']); ?> <?php echo matron_esc($_Issue['unitname']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </details>
        </div>
    </div>
</main>
<script>
(function () {
    var form = document.querySelector('#requisition-form form');
    if (!form) {
        return;
    }

    var purposeField = form.querySelector('#purpose');
    var modeField = form.querySelector('#purposemode');
    var mealField = form.querySelector('#mealtime');
    var dayField = form.querySelector('#dayname');
    var itemIdField = form.querySelector('#storeitemid');
    var itemField = form.querySelector('#storeitempicker');
    var summaryField = form.querySelector('#requestsummarydisplay');
    var previewField = form.querySelector('#matron-purpose-preview');
    var quickFill = form.querySelector('.matron-quick-fill');
    var itemList = document.getElementById('matron-storeitem-list');
    var populationLabel = quickFill ? (quickFill.getAttribute('data-population-label') || 'resident students') : 'resident students';
    var templates = {
        meal_slot: '{item} for {meal} service for {population} on {day}',
        emergency_top_up: 'Emergency stock top-up of {item} for {meal} service on {day}',
        kitchen_cleaning: 'Kitchen cleaning and sanitation supplies',
        special_diet: 'Special diet or infirmary meal support'
    };

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

    function syncHiddenItemId() {
        if (!itemIdField) {
            return;
        }
        var matchedOption = findListOptionByValue(itemList, itemField ? itemField.value : '');
        itemIdField.value = matchedOption ? (matchedOption.getAttribute('data-itemid') || '') : '';
    }

    function currentItemLabel() {
        if (!itemField) {
            return 'Selected item';
        }
        var itemValue = safeTrim(itemField.value);
        if (itemValue === '') {
            return 'Selected item';
        }
        if (itemValue.indexOf(' (') > -1) {
            itemValue = itemValue.split(' (')[0];
        }
        itemValue = safeTrim(itemValue);
        return itemValue !== '' ? itemValue : 'Selected item';
    }

    function fillTemplate(template) {
        var text = template || '';
        text = text.replace('{item}', currentItemLabel());
        text = text.replace('{meal}', mealField ? safeTrim(mealField.value) : 'Meal');
        text = text.replace('{day}', dayField ? safeTrim(dayField.value) : 'selected day');
        text = text.replace('{population}', populationLabel);
        return text;
    }

    function currentSlotLabel() {
        var meal = mealField ? safeTrim(mealField.value) : '';
        var day = dayField ? safeTrim(dayField.value) : '';
        if (meal && day) {
            return meal + ' on ' + day;
        }
        return meal || day || 'selected meal slot';
    }

    function updatePreview() {
        syncHiddenItemId();
        if (summaryField) {
            setNodeText(summaryField, currentItemLabel() + ' for ' + currentSlotLabel());
        }
        if (previewField) {
            setNodeText(previewField, 'Current request: ' + currentItemLabel() + ' for ' + currentSlotLabel());
        }
    }

    function syncPurpose(force) {
        if (!purposeField || !modeField) {
            updatePreview();
            return;
        }

        updatePreview();
        if (modeField.value === 'custom') {
            return;
        }

        var template = Object.prototype.hasOwnProperty.call(templates, modeField.value) ? templates[modeField.value] : '';
        if (template === '') {
            return;
        }

        if (!force && purposeField.dataset.autofill !== '1' && safeTrim(purposeField.value) !== '') {
            return;
        }

        purposeField.value = fillTemplate(template);
        purposeField.dataset.autofill = '1';
    }

    function handlePurposeInput() {
        if (!purposeField || !modeField) {
            return;
        }

        if (safeTrim(purposeField.value) === '') {
            modeField.value = 'meal_slot';
            purposeField.dataset.autofill = '1';
            return;
        }
        modeField.value = 'custom';
        purposeField.dataset.autofill = '0';
    }

    window.matronHandlePurposeInput = handlePurposeInput;
    window.matronSyncRequisitionFields = syncPurpose;

    var presetButtons = form.querySelectorAll('.matron-preset');
    for (var presetIndex = 0; presetIndex < presetButtons.length; presetIndex++) {
        presetButtons[presetIndex].addEventListener('click', function () {
            if (!purposeField || !modeField) {
                return;
            }

            var selectedMode = this.getAttribute('data-purpose-mode') || 'custom';
            var selectedPurpose = this.getAttribute('data-purpose') || '';
            var selectedTemplate = this.getAttribute('data-purpose-template') || '';
            modeField.value = selectedMode;

            purposeField.value = selectedPurpose !== '' ? selectedPurpose : fillTemplate(selectedTemplate);
            purposeField.dataset.autofill = selectedMode === 'custom' ? '0' : '1';
            updatePreview();
            purposeField.focus();
        });
    }

    if (purposeField && modeField) {
        purposeField.dataset.autofill = (modeField.value !== 'custom' && safeTrim(purposeField.value) !== '') ? '1' : '0';
        purposeField.addEventListener('input', handlePurposeInput);
    }

    var watchedFields = [mealField, dayField, itemField];
    for (var fieldIndex = 0; fieldIndex < watchedFields.length; fieldIndex++) {
        var field = watchedFields[fieldIndex];
        if (!field) {
            continue;
        }
        field.addEventListener('input', function () {
            syncPurpose(true);
        });
        field.addEventListener('change', function () {
            syncPurpose(true);
        });
        field.addEventListener('blur', function () {
            syncPurpose(true);
        });
    }

    updatePreview();
    if (purposeField && safeTrim(purposeField.value) === '') {
        if (modeField) {
            modeField.value = 'meal_slot';
        }
        syncPurpose(true);
    } else {
        syncPurpose(false);
    }
    form.addEventListener('submit', function () {
        syncPurpose(true);
    });
})();

(function () {
    function openHashDisclosure() {
        var hash = window.location.hash || '';
        if (!hash) {
            return;
        }

        var target = document.querySelector(hash);
        if (!target) {
            return;
        }

        var disclosure = null;
        if (target.tagName && target.tagName.toLowerCase() === 'details') {
            disclosure = target;
        } else if (typeof target.closest === 'function') {
            disclosure = target.closest('details');
        }

        if (disclosure) {
            disclosure.open = true;
        }
    }

    openHashDisclosure();
    window.addEventListener('hashchange', openHashDisclosure);
})();
</script>
</body>
</html>
