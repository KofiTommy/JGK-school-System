<?php
session_start();
$_SESSION['Message'] = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if (!house_master_is_admin()) {
    header("location:" . house_master_landing_page());
    exit();
}

if (!function_exists('hma_redirect')) {
    function hma_redirect()
    {
        header("location:house-master-assignment.php");
        exit();
    }
}

if (!function_exists('hma_esc')) {
    function hma_esc($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hma_flash_html')) {
    function hma_flash_html($tone, $message)
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

        return "<div class='hma-flash hma-flash--" . $tone . "'><span class='hma-flash__icon'><i class='fa " . $icon . "'></i></span><div class='hma-flash__body'>" . $message . "</div></div>";
    }
}

if (!function_exists('hma_status_badge_html')) {
    function hma_status_badge_html($status)
    {
        $status = strtolower(trim((string)$status));
        if ($status === 'active') {
            return "<span class='hma-badge hma-badge--active'>Active</span>";
        }
        return "<span class='hma-badge hma-badge--inactive'>Inactive</span>";
    }
}

if (isset($_POST['save_house_master'])) {
    $_TeacherId = isset($_POST['userid']) ? trim((string)$_POST['userid']) : "";
    $_HouseId = isset($_POST['houseid']) ? trim((string)$_POST['houseid']) : "";
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";

    if ($_TeacherId === '' || $_HouseId === '') {
        $_SESSION['Message'] = hma_flash_html('error', 'Please select teacher and house.');
        hma_redirect();
    }

    $_TeacherIdEsc = mysqli_real_escape_string($con, $_TeacherId);
    $_HouseIdEsc = mysqli_real_escape_string($con, $_HouseId);
    $_RecordedByEsc = mysqli_real_escape_string($con, $_RecordedBy);

    $_EXIST = mysqli_query($con, "SELECT assignmentid
        FROM tblhousemaster
        WHERE houseid='$_HouseIdEsc' AND status='active'
        LIMIT 1");
    $_HouseName = "";
    $_SQL_HN = mysqli_query($con, "SELECT housename
        FROM tblhouse
        WHERE houseid='$_HouseIdEsc'
        LIMIT 1");
    if ($_SQL_HN && ($row_hn = mysqli_fetch_array($_SQL_HN, MYSQLI_ASSOC))) {
        $_HouseName = (string)$row_hn['housename'];
    }

    if ($_EXIST && ($row_exist = mysqli_fetch_array($_EXIST, MYSQLI_ASSOC))) {
        $_AssignmentId = mysqli_real_escape_string($con, (string)$row_exist['assignmentid']);
        $_UPD = mysqli_query($con, "UPDATE tblhousemaster
            SET userid='$_TeacherIdEsc', recordedby='$_RecordedByEsc', datetimeentry=NOW(), status='active'
            WHERE assignmentid='$_AssignmentId'");
        if ($_UPD) {
            if ($_HouseName !== "") {
                notify_house_master_assignment($con, $_TeacherIdEsc, $_HouseName, $_RecordedBy, "updated");
            }
            $_SESSION['Message'] = hma_flash_html('success', 'House master assignment updated.');
        } else {
            $_SESSION['Message'] = hma_flash_html('error', 'Update failed: ' . hma_esc(mysqli_error($con)));
        }
        hma_redirect();
    }

    include("code.php");
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)$code));
    $_INS = mysqli_query($con, "INSERT INTO tblhousemaster(assignmentid,houseid,userid,status,datetimeentry,recordedby)
        VALUES('$_AssignmentId','$_HouseIdEsc','$_TeacherIdEsc','active',NOW(),'$_RecordedByEsc')");
    if ($_INS) {
        if ($_HouseName !== "") {
            notify_house_master_assignment($con, $_TeacherIdEsc, $_HouseName, $_RecordedBy, "assigned");
        }
        $_SESSION['Message'] = hma_flash_html('success', 'House master assigned successfully.');
    } else {
        $_SESSION['Message'] = hma_flash_html('error', 'Assignment failed: ' . hma_esc(mysqli_error($con)));
    }
    hma_redirect();
}

if (isset($_GET['deactivate_assignment'])) {
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)$_GET['deactivate_assignment']));
    if ($_AssignmentId !== '') {
        $_SQL = mysqli_query($con, "UPDATE tblhousemaster SET status='inactive' WHERE assignmentid='$_AssignmentId'");
        if ($_SQL) {
            $_SESSION['Message'] = hma_flash_html('warning', 'Assignment deactivated.');
        } else {
            $_SESSION['Message'] = hma_flash_html('error', 'Failed to deactivate assignment: ' . hma_esc(mysqli_error($con)));
        }
    }
    hma_redirect();
}

if (isset($_GET['delete_assignment'])) {
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)$_GET['delete_assignment']));
    if ($_AssignmentId !== '') {
        $_SQL_D = mysqli_query($con, "DELETE FROM tblhousemaster WHERE assignmentid='$_AssignmentId'");
        if ($_SQL_D) {
            $_SESSION['Message'] = hma_flash_html('warning', 'House master assignment deleted successfully.');
        } else {
            $_SESSION['Message'] = hma_flash_html('error', 'Failed to delete assignment: ' . hma_esc(mysqli_error($con)));
        }
    }
    hma_redirect();
}

$_TeacherOptions = array();
$_SQL_T = mysqli_query($con, "SELECT userid,firstname,surname,othernames
    FROM tblsystemuser
    WHERE systemtype='Teacher' AND status='active'
    ORDER BY firstname ASC,surname ASC,othernames ASC");
if ($_SQL_T) {
    while ($row_t = mysqli_fetch_array($_SQL_T, MYSQLI_ASSOC)) {
        $_TeacherOptions[] = $row_t;
    }
}

$_HouseOptions = array();
$_HouseNameMap = array();
$_SQL_H = mysqli_query($con, "SELECT houseid,housename
    FROM tblhouse
    WHERE status='active'
    ORDER BY housename ASC");
if ($_SQL_H) {
    while ($row_h = mysqli_fetch_array($_SQL_H, MYSQLI_ASSOC)) {
        $_HouseOptions[] = $row_h;
        $_HouseNameMap[(string)$row_h['houseid']] = (string)$row_h['housename'];
    }
}

$_AssignmentRows = array();
$_ActiveAssignments = 0;
$_InactiveAssignments = 0;
$_AssignedTeacherMap = array();
$_CoveredHouseMap = array();
$_SQL_A = mysqli_query($con, "SELECT hm.*,h.housename,su.firstname,su.surname,su.othernames
    FROM tblhousemaster hm
    INNER JOIN tblhouse h ON h.houseid=hm.houseid
    INNER JOIN tblsystemuser su ON su.userid=hm.userid
    ORDER BY hm.datetimeentry DESC");
if ($_SQL_A) {
    while ($row = mysqli_fetch_array($_SQL_A, MYSQLI_ASSOC)) {
        $_AssignmentRows[] = $row;
        $_AssignedTeacherMap[(string)$row['userid']] = true;
        if ((string)$row['status'] === 'active') {
            $_ActiveAssignments++;
            $_CoveredHouseMap[(string)$row['houseid']] = true;
        } else {
            $_InactiveAssignments++;
        }
    }
}

$_OpenHouseCount = 0;
foreach ($_HouseOptions as $_houseRow) {
    if (!isset($_CoveredHouseMap[(string)$_houseRow['houseid']])) {
        $_OpenHouseCount++;
    }
}

$_AssignedTeacherCount = count($_AssignedTeacherMap);
$_TotalAssignments = count($_AssignmentRows);
$_MessageHtml = $_SESSION['Message'];
$_SESSION['Message'] = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/house-master-assignment.css">
<script src="scripts/house-master-assignment.js" defer></script>
</head>
<body class="house-master-assignment-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <main class="hma-shell">
        <section class="hma-hero">
            <div class="hma-hero__copy">
                <span class="hma-kicker"><i class="fa fa-home"></i> House Master Assignment</span>
                <h1>Assign House Masters</h1>
                <p>Assign teachers to houses and manage active or past house master records from one page.</p>
                <div class="hma-hero__chips">
                    <span class="hma-chip"><i class="fa fa-users"></i> Active Teachers: <?php echo number_format((int)count($_TeacherOptions)); ?></span>
                    <span class="hma-chip"><i class="fa fa-building"></i> Active Houses: <?php echo number_format((int)count($_HouseOptions)); ?></span>
                    <span class="hma-chip"><i class="fa fa-user-secret"></i> Assigned Teachers: <?php echo number_format((int)$_AssignedTeacherCount); ?></span>
                </div>
            </div>
            <div class="hma-stats">
                <article class="hma-stat">
                    <span>Total Assignments</span>
                    <strong><?php echo number_format((int)$_TotalAssignments); ?></strong>
                    <small>Every saved house master assignment on record.</small>
                </article>
                <article class="hma-stat">
                    <span>Active Assignments</span>
                    <strong><?php echo number_format((int)$_ActiveAssignments); ?></strong>
                    <small>Houses currently covered by an active assignment.</small>
                </article>
                <article class="hma-stat">
                    <span>Inactive Assignments</span>
                    <strong><?php echo number_format((int)$_InactiveAssignments); ?></strong>
                    <small>Older records that were deactivated instead of removed.</small>
                </article>
                <article class="hma-stat hma-stat--accent">
                    <span>Open Houses</span>
                    <strong><?php echo number_format((int)$_OpenHouseCount); ?></strong>
                    <small><?php echo $_OpenHouseCount > 0 ? 'Some active houses still need a house master.' : 'All active houses currently have coverage.'; ?></small>
                </article>
            </div>
        </section>

        <div class="hma-layout">
            <aside class="hma-sidebar">
                <section class="hma-surface">
                    <div class="hma-panel-head">
                        <div>
                            <span class="hma-panel-kicker">Assignment Setup</span>
                            <h2>Assign or Update a House Master</h2>
                            <p>Select the teacher and house below. If the house already has an active assignment, saving will replace it.</p>
                        </div>
                    </div>

                    <?php if ($_MessageHtml !== "") { ?>
                    <div class="hma-message-stack"><?php echo $_MessageHtml; ?></div>
                    <?php } ?>

                    <form method="post" action="house-master-assignment.php" class="hma-form">
                        <div class="hma-form-grid">
                            <label class="hma-field">
                                <span>Teacher</span>
                                <select id="userid" name="userid" class="validate[required]">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($_TeacherOptions as $_teacherRow) {
                                        $_TeacherName = trim((string)$_teacherRow['firstname'] . " " . (string)$_teacherRow['othernames'] . " " . (string)$_teacherRow['surname']);
                                    ?>
                                    <option value="<?php echo hma_esc($_teacherRow['userid']); ?>"><?php echo hma_esc($_TeacherName); ?> (<?php echo hma_esc($_teacherRow['userid']); ?>)</option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="hma-field">
                                <span>House</span>
                                <select id="houseid" name="houseid" class="validate[required]">
                                    <option value="">Select House</option>
                                    <?php foreach ($_HouseOptions as $_houseRow) { ?>
                                    <option value="<?php echo hma_esc($_houseRow['houseid']); ?>"><?php echo hma_esc($_houseRow['housename']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>

                        <div class="hma-inline-note">
                            <i class="fa fa-info-circle"></i>
                            <span>Use this page for house-based assignments. Senior house leadership stays on the separate senior-house assignment page.</span>
                        </div>

                        <div class="hma-actions">
                            <button class="hma-btn hma-btn--primary" id="save_house_master" name="save_house_master" type="submit"><i class="fa fa-save"></i> Save Assignment</button>
                        </div>
                    </form>
                </section>
            </aside>

            <section class="hma-main">
                <section class="hma-surface">
                    <div class="hma-panel-head">
                        <div>
                            <span class="hma-panel-kicker">Assignment List</span>
                            <h2>House Master Assignments</h2>
                            <p>Review each house, the assigned teacher, and the current assignment status.</p>
                        </div>
                        <span class="hma-panel-tag"><i class="fa fa-list"></i> <?php echo number_format((int)$_TotalAssignments); ?> assignment<?php echo $_TotalAssignments === 1 ? '' : 's'; ?></span>
                    </div>

                    <div class="hma-toolbar">
                        <label class="hma-search">
                            <i class="fa fa-search"></i>
                            <input type="search" placeholder="Search house, teacher, status, or date" data-assignment-search>
                        </label>
                    </div>

                    <?php if (empty($_AssignmentRows)) { ?>
                    <div class="hma-empty-state">
                        <h3>No house master assignments yet</h3>
                        <p>Start by selecting a teacher and house from the setup panel.</p>
                    </div>
                    <?php } else { ?>
                    <div class="hma-table-wrap">
                        <table class="hma-table">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>House</th>
                                    <th>House Master</th>
                                    <th>Status</th>
                                    <th>Date / Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_AssignmentRows as $row) {
                                    $_TeacherName = trim((string)$row['firstname'] . " " . (string)$row['othernames'] . " " . (string)$row['surname']);
                                    $_SearchText = strtolower(trim((string)$row['housename'] . " " . $_TeacherName . " " . (string)$row['userid'] . " " . (string)$row['status'] . " " . (string)$row['datetimeentry']));
                                ?>
                                <tr data-assignment-row data-search="<?php echo hma_esc($_SearchText); ?>">
                                    <td data-label="Actions">
                                        <div class="hma-row-actions">
                                            <?php if ((string)$row['status'] === 'active') { ?>
                                            <a class="hma-action-btn hma-action-btn--warn" title="Deactivate assignment" onclick="return confirm('Deactivate this assignment?');" href="house-master-assignment.php?deactivate_assignment=<?php echo urlencode($row['assignmentid']); ?>"><i class="fa fa-ban"></i><span>Deactivate</span></a>
                                            <?php } ?>
                                            <a class="hma-action-btn hma-action-btn--danger" title="Delete assignment" onclick="return confirm('Delete this house-master assignment permanently?');" href="house-master-assignment.php?delete_assignment=<?php echo urlencode($row['assignmentid']); ?>"><i class="fa fa-trash"></i><span>Delete</span></a>
                                        </div>
                                    </td>
                                    <td data-label="House"><span class="hma-house-pill"><?php echo hma_esc($row['housename']); ?></span></td>
                                    <td data-label="House Master">
                                        <div class="hma-person">
                                            <strong><?php echo hma_esc($_TeacherName); ?></strong>
                                            <small><?php echo hma_esc($row['userid']); ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Status"><?php echo hma_status_badge_html($row['status']); ?></td>
                                    <td data-label="Date / Time"><?php echo hma_esc($row['datetimeentry']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="hma-empty-state hma-empty-state--inline" data-assignment-empty hidden>
                        <h3>No assignments match this search</h3>
                        <p>Try the house name, teacher name, or clear the search box.</p>
                    </div>
                    <?php } ?>
                </section>
            </section>
        </div>
    </main>
</div>
</body>
</html>
