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

if (!function_exists('sha_redirect')) {
    function sha_redirect()
    {
        header("location:senior-house-assignment.php");
        exit();
    }
}

if (!function_exists('sha_esc')) {
    function sha_esc($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sha_flash_html')) {
    function sha_flash_html($tone, $message)
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

        return "<div class='sha-flash sha-flash--" . $tone . "'><span class='sha-flash__icon'><i class='fa " . $icon . "'></i></span><div class='sha-flash__body'>" . $message . "</div></div>";
    }
}

if (!function_exists('sha_status_badge_html')) {
    function sha_status_badge_html($status)
    {
        $status = strtolower(trim((string)$status));
        if ($status === 'active') {
            return "<span class='sha-badge sha-badge--active'>Active</span>";
        }
        return "<span class='sha-badge sha-badge--inactive'>Inactive</span>";
    }
}

if (!function_exists('sha_designation_badge_html')) {
    function sha_designation_badge_html($designation)
    {
        $designation = trim((string)$designation);
        $tone = ($designation === 'Senior House Mistress') ? 'mistress' : 'master';
        return "<span class='sha-badge sha-badge--" . $tone . "'>" . sha_esc($designation) . "</span>";
    }
}

if (isset($_POST['save_senior_house'])) {
    $_TeacherId = isset($_POST['userid']) ? trim((string)$_POST['userid']) : "";
    $_Designation = isset($_POST['designation']) ? trim((string)$_POST['designation']) : "";
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";

    $_Designation = house_master_normalize_senior_designation($_Designation);

    if ($_TeacherId === '' || $_Designation === '') {
        $_SESSION['Message'] = sha_flash_html('error', 'Please select teacher and designation.');
        sha_redirect();
    }

    $_TeacherIdEsc = mysqli_real_escape_string($con, $_TeacherId);
    $_DesignationEsc = mysqli_real_escape_string($con, $_Designation);
    $_RecordedByEsc = mysqli_real_escape_string($con, $_RecordedBy);

    $_TeacherExists = mysqli_query($con, "SELECT userid
        FROM tblsystemuser
        WHERE userid='$_TeacherIdEsc' AND systemtype='Teacher' AND status='active'
        LIMIT 1");
    if (!$_TeacherExists || mysqli_num_rows($_TeacherExists) === 0) {
        $_SESSION['Message'] = sha_flash_html('error', 'Selected teacher is not active.');
        sha_redirect();
    }

    $_EXIST = mysqli_query($con, "SELECT assignmentid,userid
        FROM tblseniorhouseauthority
        WHERE designation='$_DesignationEsc' AND status='active'
        LIMIT 1");
    if ($_EXIST && ($row_exist = mysqli_fetch_array($_EXIST, MYSQLI_ASSOC))) {
        $_AssignmentId = $row_exist['assignmentid'];
        $_UPD = mysqli_query($con, "UPDATE tblseniorhouseauthority
            SET userid='$_TeacherIdEsc', recordedby='$_RecordedByEsc', datetimeentry=NOW(), status='active'
            WHERE assignmentid='" . mysqli_real_escape_string($con, $_AssignmentId) . "'");
        if ($_UPD) {
            notify_senior_house_assignment($con, $_TeacherIdEsc, $_Designation, $_RecordedBy, "updated");
            $_SESSION['Message'] = sha_flash_html('success', 'Senior house role updated successfully.');
        } else {
            $_SESSION['Message'] = sha_flash_html('error', 'Update failed: ' . sha_esc(mysqli_error($con)));
        }
        sha_redirect();
    }

    include("code.php");
    $_AssignmentId = trim((string)$code);
    $_AssignmentIdEsc = mysqli_real_escape_string($con, $_AssignmentId);
    $_INS = mysqli_query($con, "INSERT INTO tblseniorhouseauthority(assignmentid,userid,designation,status,datetimeentry,recordedby)
        VALUES('$_AssignmentIdEsc','$_TeacherIdEsc','$_DesignationEsc','active',NOW(),'$_RecordedByEsc')");
    if ($_INS) {
        notify_senior_house_assignment($con, $_TeacherIdEsc, $_Designation, $_RecordedBy, "assigned");
        $_SESSION['Message'] = sha_flash_html('success', 'Senior house role assigned successfully.');
    } else {
        $_SESSION['Message'] = sha_flash_html('error', 'Assignment failed: ' . sha_esc(mysqli_error($con)));
    }
    sha_redirect();
}

if (isset($_GET['deactivate_assignment'])) {
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)$_GET['deactivate_assignment']));
    if ($_AssignmentId !== '') {
        $_SQL = mysqli_query($con, "UPDATE tblseniorhouseauthority SET status='inactive' WHERE assignmentid='$_AssignmentId'");
        if ($_SQL) {
            $_SESSION['Message'] = sha_flash_html('warning', 'Senior house assignment deactivated.');
        } else {
            $_SESSION['Message'] = sha_flash_html('error', 'Failed to deactivate assignment: ' . sha_esc(mysqli_error($con)));
        }
    }
    sha_redirect();
}

if (isset($_GET['delete_assignment'])) {
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)$_GET['delete_assignment']));
    if ($_AssignmentId !== '') {
        $_SQL_D = mysqli_query($con, "DELETE FROM tblseniorhouseauthority WHERE assignmentid='$_AssignmentId'");
        if ($_SQL_D) {
            $_SESSION['Message'] = sha_flash_html('warning', 'Senior house assignment deleted successfully.');
        } else {
            $_SESSION['Message'] = sha_flash_html('error', 'Failed to delete assignment: ' . sha_esc(mysqli_error($con)));
        }
    }
    sha_redirect();
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

$_AssignmentRows = array();
$_ActiveAssignments = 0;
$_InactiveAssignments = 0;
$_AssignedTeacherMap = array();
$_DesignationCoverage = array(
    'Senior House Master' => false,
    'Senior House Mistress' => false,
);
$_SQL_A = mysqli_query($con, "SELECT sha.*,su.firstname,su.surname,su.othernames
    FROM tblseniorhouseauthority sha
    INNER JOIN tblsystemuser su ON su.userid=sha.userid
    ORDER BY
        CASE WHEN sha.designation='Senior House Master' THEN 0 ELSE 1 END ASC,
        sha.datetimeentry DESC");
if ($_SQL_A) {
    while ($row = mysqli_fetch_array($_SQL_A, MYSQLI_ASSOC)) {
        $_AssignmentRows[] = $row;
        $_AssignedTeacherMap[(string)$row['userid']] = true;
        if ((string)$row['status'] === 'active') {
            $_ActiveAssignments++;
            $_DesignationCoverage[(string)$row['designation']] = true;
        } else {
            $_InactiveAssignments++;
        }
    }
}

$_AssignedTeacherCount = count($_AssignedTeacherMap);
$_TotalAssignments = count($_AssignmentRows);
$_OpenDesignationCount = 0;
foreach ($_DesignationCoverage as $_isCovered) {
    if (!$_isCovered) {
        $_OpenDesignationCount++;
    }
}

$_MessageHtml = $_SESSION['Message'];
$_SESSION['Message'] = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/senior-house-assignment.css">
<script src="scripts/senior-house-assignment.js" defer></script>
</head>
<body class="senior-house-assignment-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <main class="sha-shell">
        <section class="sha-hero">
            <div class="sha-hero__copy">
                <span class="sha-kicker"><i class="fa fa-shield"></i> Senior House Leadership</span>
                <h1>Assign Senior House Roles</h1>
                <p>Assign teachers to the school's senior house leadership roles and manage active or past appointments from one page.</p>
                <div class="sha-hero__chips">
                    <span class="sha-chip"><i class="fa fa-users"></i> Active Teachers: <?php echo number_format((int)count($_TeacherOptions)); ?></span>
                    <span class="sha-chip"><i class="fa fa-user-secret"></i> Assigned Teachers: <?php echo number_format((int)$_AssignedTeacherCount); ?></span>
                    <span class="sha-chip"><i class="fa fa-bookmark"></i> Roles: 2 official designations</span>
                </div>
            </div>
            <div class="sha-stats">
                <article class="sha-stat">
                    <span>Total Assignments</span>
                    <strong><?php echo number_format((int)$_TotalAssignments); ?></strong>
                    <small>Every saved senior house assignment on record.</small>
                </article>
                <article class="sha-stat">
                    <span>Active Roles</span>
                    <strong><?php echo number_format((int)$_ActiveAssignments); ?></strong>
                    <small>Senior house roles currently assigned and active.</small>
                </article>
                <article class="sha-stat">
                    <span>Inactive Roles</span>
                    <strong><?php echo number_format((int)$_InactiveAssignments); ?></strong>
                    <small>Older assignments that were deactivated instead of removed.</small>
                </article>
                <article class="sha-stat sha-stat--accent">
                    <span>Open Designations</span>
                    <strong><?php echo number_format((int)$_OpenDesignationCount); ?></strong>
                    <small><?php echo $_OpenDesignationCount > 0 ? 'One or more leadership roles still need an active teacher.' : 'Both leadership roles are currently covered.'; ?></small>
                </article>
            </div>
        </section>

        <div class="sha-layout">
            <aside class="sha-sidebar">
                <section class="sha-surface">
                    <div class="sha-panel-head">
                        <div>
                            <span class="sha-panel-kicker">Assignment Setup</span>
                            <h2>Assign or Update a Senior House Role</h2>
                            <p>Select the teacher and designation below. If the role is already active, saving will replace the current assignment.</p>
                        </div>
                    </div>

                    <?php if ($_MessageHtml !== "") { ?>
                    <div class="sha-message-stack"><?php echo $_MessageHtml; ?></div>
                    <?php } ?>

                    <form method="post" action="senior-house-assignment.php" class="sha-form">
                        <div class="sha-form-grid">
                            <label class="sha-field">
                                <span>Teacher</span>
                                <select id="userid" name="userid" class="validate[required]">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($_TeacherOptions as $_teacherRow) {
                                        $_TeacherName = trim((string)$_teacherRow['firstname'] . " " . (string)$_teacherRow['othernames'] . " " . (string)$_teacherRow['surname']);
                                    ?>
                                    <option value="<?php echo sha_esc($_teacherRow['userid']); ?>"><?php echo sha_esc($_TeacherName); ?> (<?php echo sha_esc($_teacherRow['userid']); ?>)</option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="sha-field">
                                <span>Designation</span>
                                <select id="designation" name="designation" class="validate[required]">
                                    <option value="">Select Designation</option>
                                    <option value="Senior House Master">Senior House Master</option>
                                    <option value="Senior House Mistress">Senior House Mistress</option>
                                </select>
                            </label>
                        </div>

                        <div class="sha-inline-note">
                            <i class="fa fa-info-circle"></i>
                            <span>Use this page for only the top house leadership roles. Class and house-specific assignments should stay in their own workflow.</span>
                        </div>

                        <div class="sha-actions">
                            <button class="sha-btn sha-btn--primary" id="save_senior_house" name="save_senior_house" type="submit"><i class="fa fa-save"></i> Save Assignment</button>
                        </div>
                    </form>
                </section>
            </aside>

            <section class="sha-main">
                <section class="sha-surface">
                    <div class="sha-panel-head">
                        <div>
                            <span class="sha-panel-kicker">Assignment List</span>
                            <h2>Senior House Assignments</h2>
                            <p>Review each senior house role, the teacher assigned to it, and the current assignment status.</p>
                        </div>
                        <span class="sha-panel-tag"><i class="fa fa-list"></i> <?php echo number_format((int)$_TotalAssignments); ?> assignment<?php echo $_TotalAssignments === 1 ? '' : 's'; ?></span>
                    </div>

                    <div class="sha-toolbar">
                        <label class="sha-search">
                            <i class="fa fa-search"></i>
                            <input type="search" placeholder="Search teacher, designation, status, or date" data-assignment-search>
                        </label>
                    </div>

                    <?php if (empty($_AssignmentRows)) { ?>
                    <div class="sha-empty-state">
                        <h3>No senior house assignments yet</h3>
                        <p>Start by selecting a teacher and one of the two senior house leadership roles from the setup panel.</p>
                    </div>
                    <?php } else { ?>
                    <div class="sha-table-wrap">
                        <table class="sha-table">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>Designation</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                    <th>Date / Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_AssignmentRows as $row) {
                                    $_TeacherName = trim((string)$row['firstname'] . " " . (string)$row['othernames'] . " " . (string)$row['surname']);
                                    $_SearchText = strtolower(trim($_TeacherName . " " . (string)$row['userid'] . " " . (string)$row['designation'] . " " . (string)$row['status'] . " " . (string)$row['datetimeentry']));
                                ?>
                                <tr data-assignment-row data-search="<?php echo sha_esc($_SearchText); ?>">
                                    <td data-label="Actions">
                                        <div class="sha-row-actions">
                                            <?php if ((string)$row['status'] === 'active') { ?>
                                            <a class="sha-action-btn sha-action-btn--warn" title="Deactivate assignment" onclick="return confirm('Deactivate this senior house assignment?');" href="senior-house-assignment.php?deactivate_assignment=<?php echo urlencode($row['assignmentid']); ?>"><i class="fa fa-ban"></i><span>Deactivate</span></a>
                                            <?php } ?>
                                            <a class="sha-action-btn sha-action-btn--danger" title="Delete assignment" onclick="return confirm('Delete this senior house assignment permanently?');" href="senior-house-assignment.php?delete_assignment=<?php echo urlencode($row['assignmentid']); ?>"><i class="fa fa-trash"></i><span>Delete</span></a>
                                        </div>
                                    </td>
                                    <td data-label="Designation"><?php echo sha_designation_badge_html($row['designation']); ?></td>
                                    <td data-label="Teacher">
                                        <div class="sha-person">
                                            <strong><?php echo sha_esc($_TeacherName); ?></strong>
                                            <small><?php echo sha_esc($row['userid']); ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Status"><?php echo sha_status_badge_html($row['status']); ?></td>
                                    <td data-label="Date / Time"><?php echo sha_esc($row['datetimeentry']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="sha-empty-state sha-empty-state--inline" data-assignment-empty hidden>
                        <h3>No assignments match this search</h3>
                        <p>Try the teacher name, the designation title, or clear the search box.</p>
                    </div>
                    <?php } ?>
                </section>
            </section>
        </div>
    </main>
</div>
</body>
</html>
