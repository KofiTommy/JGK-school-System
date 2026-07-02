<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if (!house_master_can_manage_module($con, 'house_management')) {
    header("location:" . house_master_landing_page());
    exit();
}

if (!function_exists('he_esc')) {
    function he_esc($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('he_flash_html')) {
    function he_flash_html($tone, $message)
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

        return "<div class='he-flash he-flash--" . $tone . "'><span class='he-flash__icon'><i class='fa " . $icon . "'></i></span><div class='he-flash__body'>" . $message . "</div></div>";
    }
}

if (!function_exists('he_status_badge_html')) {
    function he_status_badge_html($status)
    {
        $status = strtolower(trim((string)$status));
        if ($status === 'active') {
            return "<span class='he-badge he-badge--active'>Active</span>";
        }
        return "<span class='he-badge he-badge--inactive'>Inactive</span>";
    }
}

$_Message = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
unset($_SESSION['Message']);

$_Form = array(
    "houseid" => "",
    "housename" => "",
    "description" => ""
);
$_EditingHouse = false;

if (isset($_GET['edit_house'])) {
    $_EditHouseId = mysqli_real_escape_string($con, trim((string)$_GET['edit_house']));
    if ($_EditHouseId !== "") {
        $_EDIT_RES = mysqli_query($con, "SELECT houseid,housename,description
            FROM tblhouse
            WHERE houseid='$_EditHouseId'
            LIMIT 1");
        if ($_EDIT_RES && ($_EDIT_ROW = mysqli_fetch_array($_EDIT_RES, MYSQLI_ASSOC))) {
            $_Form["houseid"] = (string)$_EDIT_ROW["houseid"];
            $_Form["housename"] = (string)$_EDIT_ROW["housename"];
            $_Form["description"] = (string)$_EDIT_ROW["description"];
            $_EditingHouse = true;
        } else {
            $_Message = he_flash_html('error', 'The selected house could not be found.');
        }
    }
}

if (isset($_POST['save_house'])) {
    $_Form["houseid"] = trim((string)(isset($_POST['houseid']) ? $_POST['houseid'] : ""));
    $_Form["housename"] = trim((string)(isset($_POST['housename']) ? $_POST['housename'] : ""));
    $_Form["description"] = trim((string)(isset($_POST['description']) ? $_POST['description'] : ""));
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
    $_EditingHouse = $_Form["houseid"] !== "";

    if ($_Form["housename"] === "") {
        $_Message = he_flash_html('error', 'House name is required.');
    } else {
        $_HouseIdEsc = mysqli_real_escape_string($con, $_Form["houseid"]);
        $_HouseNameEsc = mysqli_real_escape_string($con, $_Form["housename"]);
        $_DescriptionEsc = mysqli_real_escape_string($con, $_Form["description"]);
        $_RecordedByEsc = mysqli_real_escape_string($con, $_RecordedBy);

        $_CHK_SQL = "SELECT houseid FROM tblhouse WHERE housename='$_HouseNameEsc'";
        if ($_EditingHouse) {
            $_CHK_SQL .= " AND houseid<>'$_HouseIdEsc'";
        }
        $_CHK_SQL .= " LIMIT 1";
        $_CHK = mysqli_query($con, $_CHK_SQL);
        if ($_CHK && mysqli_num_rows($_CHK) > 0) {
            $_Message = he_flash_html('error', 'House already exists.');
        } else {
            if ($_EditingHouse) {
                $_SQL = mysqli_query($con, "UPDATE tblhouse
                    SET housename='$_HouseNameEsc', description='$_DescriptionEsc'
                    WHERE houseid='$_HouseIdEsc'
                    LIMIT 1");
                if ($_SQL) {
                    $_SESSION['Message'] = he_flash_html('success', 'House updated successfully. Existing assignments remain intact.');
                    header("location:house-entry.php");
                    exit();
                }
                $_Message = he_flash_html('error', 'Failed to update house: ' . he_esc(mysqli_error($con)));
            } else {
                include("code.php");
                $_HouseId = mysqli_real_escape_string($con, trim((string)$code));
                $_SQL = mysqli_query($con, "INSERT INTO tblhouse(houseid,housename,description,status,datetimeentry,recordedby)
                    VALUES('$_HouseId','$_HouseNameEsc','$_DescriptionEsc','active',NOW(),'$_RecordedByEsc')");
                if ($_SQL) {
                    $_SESSION['Message'] = he_flash_html('success', 'House saved successfully.');
                    header("location:house-entry.php");
                    exit();
                }
                $_Message = he_flash_html('error', 'Failed to save house: ' . he_esc(mysqli_error($con)));
            }
        }
    }
}

if (isset($_GET['deactivate_house'])) {
    $_HouseId = mysqli_real_escape_string($con, trim((string)$_GET['deactivate_house']));
    if ($_HouseId !== "") {
        $_SQL = mysqli_query($con, "UPDATE tblhouse SET status='inactive' WHERE houseid='$_HouseId'");
        if ($_SQL) {
            $_SESSION['Message'] = he_flash_html('warning', 'House deactivated.');
        } else {
            $_SESSION['Message'] = he_flash_html('error', 'Failed to deactivate house: ' . he_esc(mysqli_error($con)));
        }
    }
    header("location:house-entry.php");
    exit();
}

if (isset($_GET['delete_house'])) {
    $_HouseId = mysqli_real_escape_string($con, trim((string)$_GET['delete_house']));

    $_CHK_STUDENT = mysqli_query($con, "SELECT assignmentid FROM tblstudenthouse WHERE houseid='$_HouseId' AND status='active' LIMIT 1");
    $_CHK_MASTER = mysqli_query($con, "SELECT assignmentid FROM tblhousemaster WHERE houseid='$_HouseId' AND status='active' LIMIT 1");
    $_CHK_EXEAT = mysqli_query($con, "SELECT exeatid FROM tblexeatrequest WHERE houseid='$_HouseId' LIMIT 1");

    if (($_CHK_STUDENT && mysqli_num_rows($_CHK_STUDENT) > 0) || ($_CHK_MASTER && mysqli_num_rows($_CHK_MASTER) > 0)) {
        $_SESSION['Message'] = he_flash_html('error', 'Cannot delete house: remove active student and house-master assignments first.');
    } elseif ($_CHK_EXEAT && mysqli_num_rows($_CHK_EXEAT) > 0) {
        $_SESSION['Message'] = he_flash_html('error', 'Cannot delete house: exeat history exists for this house.');
    } else {
        $_SQL_D = mysqli_query($con, "DELETE FROM tblhouse WHERE houseid='$_HouseId'");
        if ($_SQL_D) {
            $_SESSION['Message'] = he_flash_html('warning', 'House deleted successfully.');
        } else {
            $_SESSION['Message'] = he_flash_html('error', 'Failed to delete house: ' . he_esc(mysqli_error($con)));
        }
    }
    header("location:house-entry.php");
    exit();
}

$_HouseRows = array();
$_TotalHouses = 0;
$_ActiveHouses = 0;
$_InactiveHouses = 0;
$_CoveredHouses = 0;
$_StudentLinkedHouses = 0;

$_SQL_H = mysqli_query($con, "SELECT
        h.*,
        (
            SELECT COUNT(*)
            FROM tblhousemaster hm
            WHERE hm.houseid=h.houseid
              AND hm.status='active'
        ) AS active_house_master_count,
        (
            SELECT COUNT(*)
            FROM tblstudenthouse sh
            WHERE sh.houseid=h.houseid
              AND sh.status='active'
        ) AS active_student_count
    FROM tblhouse h
    ORDER BY h.datetimeentry DESC");
if ($_SQL_H) {
    while ($row = mysqli_fetch_array($_SQL_H, MYSQLI_ASSOC)) {
        $_HouseRows[] = $row;
        $_TotalHouses++;
        if ((string)$row['status'] === 'active') {
            $_ActiveHouses++;
        } else {
            $_InactiveHouses++;
        }
        if ((int)$row['active_house_master_count'] > 0) {
            $_CoveredHouses++;
        }
        if ((int)$row['active_student_count'] > 0) {
            $_StudentLinkedHouses++;
        }
    }
}

$_OpenHouses = max(0, $_ActiveHouses - $_CoveredHouses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/house-entry.css">
<script src="scripts/house-entry.js" defer></script>
</head>
<body class="house-entry-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <main class="he-shell">
        <section class="he-hero">
            <div class="he-hero__copy">
                <span class="he-kicker"><i class="fa fa-building"></i> House Records</span>
                <h1>Manage School Houses</h1>
                <p>Add new houses, update existing records, and review whether each house is already linked to a house master or students.</p>
                <div class="he-hero__chips">
                    <span class="he-chip"><i class="fa fa-home"></i> Total Houses: <?php echo number_format((int)$_TotalHouses); ?></span>
                    <span class="he-chip"><i class="fa fa-user-secret"></i> Covered Houses: <?php echo number_format((int)$_CoveredHouses); ?></span>
                    <span class="he-chip"><i class="fa fa-users"></i> Student-linked Houses: <?php echo number_format((int)$_StudentLinkedHouses); ?></span>
                </div>
            </div>
            <div class="he-stats">
                <article class="he-stat">
                    <span>Active Houses</span>
                    <strong><?php echo number_format((int)$_ActiveHouses); ?></strong>
                    <small>Houses currently available for assignments and student placement.</small>
                </article>
                <article class="he-stat">
                    <span>Inactive Houses</span>
                    <strong><?php echo number_format((int)$_InactiveHouses); ?></strong>
                    <small>Saved houses that were deactivated instead of removed.</small>
                </article>
                <article class="he-stat">
                    <span>House Coverage</span>
                    <strong><?php echo number_format((int)$_CoveredHouses); ?></strong>
                    <small>Houses with at least one active house-master assignment.</small>
                </article>
                <article class="he-stat he-stat--accent">
                    <span>Open Houses</span>
                    <strong><?php echo number_format((int)$_OpenHouses); ?></strong>
                    <small><?php echo $_OpenHouses > 0 ? 'Some active houses still need a house master.' : 'Every active house currently has coverage.'; ?></small>
                </article>
            </div>
        </section>

        <div class="he-layout">
            <aside class="he-sidebar">
                <section class="he-surface">
                    <div class="he-panel-head">
                        <div>
                            <span class="he-panel-kicker">House Setup</span>
                            <h2><?php echo $_EditingHouse ? 'Edit House' : 'Create House'; ?></h2>
                            <p><?php echo $_EditingHouse ? 'Update the selected house record. Existing assignments will remain linked to the same house ID after you save.' : 'Enter the house name and optional description to add a new house record.'; ?></p>
                        </div>
                    </div>

                    <?php if ($_Message !== "") { ?>
                    <div class="he-message-stack"><?php echo $_Message; ?></div>
                    <?php } ?>

                    <form method="post" action="house-entry.php" class="he-form">
                        <input type="hidden" name="houseid" value="<?php echo he_esc($_Form['houseid']); ?>">

                        <div class="he-form-grid">
                            <label class="he-field">
                                <span>House Name</span>
                                <input type="text" id="housename" name="housename" class="validate[required]" value="<?php echo he_esc($_Form['housename']); ?>" placeholder="Enter house name">
                            </label>

                            <label class="he-field">
                                <span>Description</span>
                                <textarea id="description" name="description" rows="6" placeholder="Add a short note about this house"><?php echo he_esc($_Form['description']); ?></textarea>
                            </label>
                        </div>

                        <?php if ($_EditingHouse) { ?>
                        <div class="he-inline-note">
                            <i class="fa fa-info-circle"></i>
                            <span>You are editing this house name. Existing house assignments will continue to use the same house record.</span>
                        </div>
                        <?php } ?>

                        <div class="he-actions">
                            <button class="he-btn he-btn--primary" id="save_house" name="save_house" type="submit"><i class="fa fa-save"></i> <?php echo $_EditingHouse ? "Update House" : "Save House"; ?></button>
                            <?php if ($_EditingHouse) { ?>
                            <a class="he-btn he-btn--secondary" href="house-entry.php"><i class="fa fa-times"></i> Cancel</a>
                            <?php } ?>
                        </div>
                    </form>
                </section>
            </aside>

            <section class="he-main">
                <section class="he-surface">
                    <div class="he-panel-head">
                        <div>
                            <span class="he-panel-kicker">House List</span>
                            <h2>House Records</h2>
                            <p>Review each house, its description, linked assignments, and current status.</p>
                        </div>
                        <span class="he-panel-tag"><i class="fa fa-list"></i> <?php echo number_format((int)$_TotalHouses); ?> house<?php echo $_TotalHouses === 1 ? '' : 's'; ?></span>
                    </div>

                    <div class="he-toolbar">
                        <label class="he-search">
                            <i class="fa fa-search"></i>
                            <input type="search" placeholder="Search house, description, status, or date" data-house-search>
                        </label>
                    </div>

                    <?php if (empty($_HouseRows)) { ?>
                    <div class="he-empty-state">
                        <h3>No houses yet</h3>
                        <p>Create your first house from the setup panel to begin assigning students and house masters.</p>
                    </div>
                    <?php } else { ?>
                    <div class="he-table-wrap">
                        <table class="he-table">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>House</th>
                                    <th>Description</th>
                                    <th>Linked Activity</th>
                                    <th>Status</th>
                                    <th>Date / Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_HouseRows as $row) {
                                    $_LinkedSummary = ((int)$row['active_house_master_count'] > 0 ? 'Has house master' : 'No house master') . ' / ' . number_format((int)$row['active_student_count']) . ' active student(s)';
                                    $_SearchText = strtolower(trim((string)$row['housename'] . " " . (string)$row['description'] . " " . (string)$row['status'] . " " . (string)$row['datetimeentry'] . " " . $_LinkedSummary));
                                ?>
                                <tr data-house-row data-search="<?php echo he_esc($_SearchText); ?>">
                                    <td data-label="Actions">
                                        <div class="he-row-actions">
                                            <a class="he-action-btn he-action-btn--edit" title="Edit house" href="house-entry.php?edit_house=<?php echo urlencode($row['houseid']); ?>"><i class="fa fa-pencil"></i><span>Edit</span></a>
                                            <?php if ((string)$row['status'] === 'active') { ?>
                                            <a class="he-action-btn he-action-btn--warn" title="Deactivate house" onclick="return confirm('Deactivate this house?');" href="house-entry.php?deactivate_house=<?php echo urlencode($row['houseid']); ?>"><i class="fa fa-ban"></i><span>Deactivate</span></a>
                                            <?php } ?>
                                            <a class="he-action-btn he-action-btn--danger" title="Delete house" onclick="return confirm('Delete this house permanently?');" href="house-entry.php?delete_house=<?php echo urlencode($row['houseid']); ?>"><i class="fa fa-trash"></i><span>Delete</span></a>
                                        </div>
                                    </td>
                                    <td data-label="House">
                                        <div class="he-house">
                                            <strong><?php echo he_esc($row['housename']); ?></strong>
                                            <small><?php echo he_esc($row['houseid']); ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Description"><?php echo he_esc($row['description'] !== '' ? $row['description'] : 'No description added'); ?></td>
                                    <td data-label="Linked Activity">
                                        <div class="he-meta-stack">
                                            <?php if ((int)$row['active_house_master_count'] > 0) { ?>
                                            <span class="he-badge he-badge--info">House master assigned</span>
                                            <?php } else { ?>
                                            <span class="he-badge he-badge--muted">No house master</span>
                                            <?php } ?>
                                            <span class="he-badge he-badge--count"><?php echo number_format((int)$row['active_student_count']); ?> active student<?php echo ((int)$row['active_student_count'] === 1 ? '' : 's'); ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Status"><?php echo he_status_badge_html($row['status']); ?></td>
                                    <td data-label="Date / Time"><?php echo he_esc($row['datetimeentry']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="he-empty-state he-empty-state--inline" data-house-empty hidden>
                        <h3>No houses match this search</h3>
                        <p>Try a house name, part of the description, or clear the search box.</p>
                    </div>
                    <?php } ?>
                </section>
            </section>
        </div>
    </main>
</div>
</body>
</html>
