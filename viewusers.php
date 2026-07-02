<?php
session_start();

include("check-login.php");
include_once("user-management-utils.php");

if (!isset($_SESSION['Message'])) {
    $_SESSION['Message'] = "";
}

function teacher_list_safe($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function teacher_list_format_date($value, $format)
{
    if (empty($value)) {
        return "--";
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date($format, $timestamp);
}

function teacher_list_redirect()
{
    header("Location: viewusers.php");
    exit;
}

$isHeadmasterViewer = function_exists('um_is_headmaster_user') && um_is_headmaster_user();
$canManageTeachers = function_exists('um_is_admin_manager') && um_is_admin_manager();

if (!$isHeadmasterViewer && !$canManageTeachers) {
    $_SESSION['Message'] = "<div class='teacher-directory-flash teacher-directory-flash--error'>You do not have access to the teachers list.</div>";
    header("Location: " . (function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php"));
    exit;
}

$branchId = isset($_SESSION['BRANCHID']) ? trim((string)$_SESSION['BRANCHID']) : "";
$teacherListScope = "";
if ($branchId !== "") {
    $branchEsc = mysqli_real_escape_string($con, $branchId);
    $teacherListScope = " AND branchid='" . $branchEsc . "'";
}

@$_UserID = $_POST['userid'];
@$_Firstname = $_POST['firstname'];
@$_Surname = $_POST['surname'];
@$_Othernames = $_POST['othernames'];
@$_Gender = $_POST['gender'];
@$_Birthday = $_POST['birthday'];
@$_Age = $_POST['age'];
@$_PostalAddress = $_POST['postaladdress'];
@$_HomeAddress = $_POST['homeaddress'];
@$_HomeTown = $_POST['hometown'];
@$_Religion = $_POST['religion'];
@$_Relationship = $_POST['relationship'];
@$_Nextofkin_fullname = $_POST['nextoffullname'];
@$_Nextofcontact = $_POST['nextofkincontact'];
@$_Username = $_POST['username'];
@$_Password = $_POST['password'];
@$_AccessLevel = "user";
@$_SystemType = $_POST['systemtype'];

if ($canManageTeachers && isset($_POST['register_user'])) {
    $_SQL_EXECUTE = mysqli_query(
        $con,
        "INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,relationship,nextofkin_fullname,nextofkin_contact,registereddatetime,status,username,password,accesslevel,systemtype)
        VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender',STR_TO_DATE('$_Birthday','%d-%m-%Y'),'$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_Nextofkin_fullname','$_Nextofcontact',NOW(),'active','$_Username','$_Password','$_AccessLevel','$_SystemType')"
    );

    if ($_SQL_EXECUTE) {
        $_SESSION['Message'] = "<div class='teacher-directory-flash teacher-directory-flash--success'>Teacher account saved successfully.</div>";
    } else {
        $_SESSION['Message'] = "<div class='teacher-directory-flash teacher-directory-flash--error'>Teacher account failed to save. Error: " . teacher_list_safe(mysqli_error($con)) . "</div>";
    }

    teacher_list_redirect();
}

if ($canManageTeachers && isset($_GET["block_user"])) {
    $_SQL_EXECUTE = mysqli_query($con, "UPDATE tblsystemuser SET status='block' WHERE userid='" . mysqli_real_escape_string($con, $_GET["block_user"]) . "'");

    if ($_SQL_EXECUTE) {
        $_SESSION['Message'] = "<div class='teacher-directory-flash teacher-directory-flash--warning'>Teacher account has been blocked.</div>";
    } else {
        $_SESSION['Message'] = "<div class='teacher-directory-flash teacher-directory-flash--error'>Teacher account could not be blocked.</div>";
    }

    teacher_list_redirect();
}

if ($canManageTeachers && isset($_GET["unblock_user"])) {
    $_SQL_EXECUTE = mysqli_query($con, "UPDATE tblsystemuser SET status='active' WHERE userid='" . mysqli_real_escape_string($con, $_GET["unblock_user"]) . "'");

    if ($_SQL_EXECUTE) {
        $_SESSION['Message'] = "<div class='teacher-directory-flash teacher-directory-flash--success'>Teacher account is active again.</div>";
    } else {
        $_SESSION['Message'] = "<div class='teacher-directory-flash teacher-directory-flash--error'>Teacher account could not be reactivated.</div>";
    }

    teacher_list_redirect();
}

if ($canManageTeachers && isset($_GET["delete_user"])) {
    $_SQL_EXECUTE = mysqli_query($con, "DELETE FROM tblsystemuser WHERE userid='" . mysqli_real_escape_string($con, $_GET["delete_user"]) . "'");

    if ($_SQL_EXECUTE) {
        $_SESSION['Message'] = "<div class='teacher-directory-flash teacher-directory-flash--warning'>Teacher record deleted successfully.</div>";
    } else {
        $_SESSION['Message'] = "<div class='teacher-directory-flash teacher-directory-flash--error'>Teacher record could not be deleted.</div>";
    }

    teacher_list_redirect();
}

$_FlashMessage = $_SESSION['Message'];
$_SESSION['Message'] = "";

$_Teachers = array();
$_TeacherCount = 0;
$_ActiveTeacherCount = 0;
$_BlockedTeacherCount = 0;
$_TeachingStaffCount = 0;

$_SQL_EXECUTE = mysqli_query($con, "SELECT * FROM tblsystemuser WHERE systemtype='Teacher' $teacherListScope ORDER BY firstname ASC, surname ASC, othernames ASC");

if ($_SQL_EXECUTE) {
    while ($row = mysqli_fetch_array($_SQL_EXECUTE, MYSQLI_ASSOC)) {
        $_Teachers[] = $row;
        $_TeacherCount++;

        if (isset($row['status']) && strtolower($row['status']) === 'active') {
            $_ActiveTeacherCount++;
        } else {
            $_BlockedTeacherCount++;
        }

        if (!empty($row['staffstatus'])) {
            $_TeachingStaffCount++;
        }
    }
} else {
    $_FlashMessage = "<div class='teacher-directory-flash teacher-directory-flash--error'>The teacher list could not be loaded right now.</div>";
}
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/viewusers.css?v=20260426">
</head>
<body class="teacher-directory-page">
    <div class="header">
    <?php include("menu.php"); ?>
    </div>

    <main class="teacher-directory-shell">
        <?php if (!empty($_FlashMessage)) { ?>
            <div class="teacher-directory-message">
                <?php echo $_FlashMessage; ?>
            </div>
        <?php } ?>

        <section class="teacher-directory-hero">
            <div class="teacher-directory-hero__copy">
                <span class="teacher-directory-eyebrow">Staff Directory</span>
                <h1>Teachers List</h1>
                <p><?php echo $isHeadmasterViewer ? "Review the teacher directory, open staff profiles, and print the list when needed." : "Review the full teacher directory, search quickly, and print a staff list when needed."; ?></p>
            </div>
            <div class="teacher-directory-hero__actions">
                <?php if ($canManageTeachers) { ?>
                <a href="register-teacher.php" class="teacher-directory-link">
                    <i class="fa fa-user-plus"></i> Register Teacher
                </a>
                <?php } ?>
                <button type="button" class="teacher-directory-print-btn" onclick="window.print()">
                    <i class="fa fa-print"></i> Print Teachers List
                </button>
            </div>
        </section>

        <section class="teacher-directory-stats" aria-label="Teacher Summary">
            <article class="teacher-directory-stat">
                <span>Total Teachers</span>
                <strong><?php echo number_format($_TeacherCount); ?></strong>
            </article>
            <article class="teacher-directory-stat">
                <span>Active</span>
                <strong><?php echo number_format($_ActiveTeacherCount); ?></strong>
            </article>
            <article class="teacher-directory-stat">
                <span>Blocked</span>
                <strong><?php echo number_format($_BlockedTeacherCount); ?></strong>
            </article>
            <article class="teacher-directory-stat">
                <span>With Staff Status</span>
                <strong><?php echo number_format($_TeachingStaffCount); ?></strong>
            </article>
        </section>

        <section class="teacher-directory-panel">
            <div class="teacher-directory-panel__top">
                <div>
                    <span class="teacher-directory-eyebrow">Directory Tools</span>
                    <h2>Teacher Register</h2>
                </div>
                <div class="teacher-directory-print-note">
                    Print uses the teachers currently visible on the screen.
                </div>
            </div>

            <div class="teacher-directory-toolbar">
                <label class="teacher-directory-search">
                    <i class="fa fa-search"></i>
                    <input type="text" id="teacherDirectorySearch" placeholder="Search by teacher name, ID, username, gender, status, or staff status">
                </label>

                <div class="teacher-directory-filters" aria-label="Status Filter">
                    <button type="button" class="teacher-directory-filter is-active" data-filter="all">All</button>
                    <button type="button" class="teacher-directory-filter" data-filter="active">Active</button>
                    <button type="button" class="teacher-directory-filter" data-filter="block">Blocked</button>
                </div>

                <div class="teacher-directory-results" id="teacherDirectoryResultCount">
                    <?php echo number_format($_TeacherCount); ?> teachers shown
                </div>
            </div>

            <div class="teacher-directory-print-head">
                <h2>Ayirebi Senior High School</h2>
                <p>Teachers List</p>
                <span>Printed on <?php echo date("d M Y, g:i a"); ?></span>
            </div>

            <div class="teacher-directory-table-wrap">
                <table class="teacher-directory-table" id="teacherDirectoryTable">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Gender</th>
                            <th>Birthday</th>
                            <th>Username</th>
                            <th>Staff Status</th>
                            <th>Entry Date</th>
                            <th>Status</th>
                            <th class="teacher-directory-actions-col">Task</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_Teachers as $row) {
                            $_Fullname = trim($row['firstname'] . " " . $row['surname'] . " " . $row['othernames']);
                            $_Status = strtolower((string)$row['status']);
                            $_BirthdayLabel = teacher_list_format_date($row['birthday'], "d M Y");
                            $_EntryLabel = teacher_list_format_date($row['registereddatetime'], "d M Y, g:i a");
                            $_SearchIndex = strtolower(trim($_Fullname . " " . $row['userid'] . " " . $row['username'] . " " . $row['gender'] . " " . $row['status'] . " " . $row['staffstatus']));
                        ?>
                            <tr class="teacher-directory-row" data-status="<?php echo teacher_list_safe($_Status); ?>" data-search="<?php echo teacher_list_safe($_SearchIndex); ?>">
                                <td data-label="Teacher">
                                    <div class="teacher-directory-name">
                                        <strong><?php echo teacher_list_safe($_Fullname); ?></strong>
                                        <span>ID: <?php echo teacher_list_safe($row['userid']); ?></span>
                                    </div>
                                </td>
                                <td data-label="Gender"><?php echo teacher_list_safe($row['gender']); ?></td>
                                <td data-label="Birthday"><?php echo teacher_list_safe($_BirthdayLabel); ?></td>
                                <td data-label="Username"><?php echo teacher_list_safe($row['username']); ?></td>
                                <td data-label="Staff Status">
                                    <?php echo (!empty($row['staffstatus']) ? teacher_list_safe($row['staffstatus']) : "--"); ?>
                                </td>
                                <td data-label="Entry Date"><?php echo teacher_list_safe($_EntryLabel); ?></td>
                                <td data-label="Status">
                                    <span class="teacher-directory-badge teacher-directory-badge--<?php echo ($_Status === 'active' ? 'active' : 'blocked'); ?>">
                                        <?php echo ($_Status === 'active' ? 'Active' : 'Blocked'); ?>
                                    </span>
                                </td>
                                <td data-label="Task" class="teacher-directory-actions-col">
                                    <div class="teacher-directory-actions">
                                        <a class="teacher-directory-action teacher-directory-action--view" title="View <?php echo teacher_list_safe($_Fullname); ?>" href="user-profile.php?view_user=<?php echo urlencode($row['userid']); ?>">
                                            <i class="fa fa-book"></i><span>View</span>
                                        </a>
                                        <?php if ($canManageTeachers) { ?>
                                        <a class="teacher-directory-action teacher-directory-action--edit" title="Edit <?php echo teacher_list_safe($_Fullname); ?>" href="register_edit.php?edit_user=<?php echo urlencode($row['userid']); ?>">
                                            <i class="fa fa-edit"></i><span>Edit</span>
                                        </a>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <div class="teacher-directory-empty" id="teacherDirectoryEmpty" hidden>
                    <i class="fa fa-search"></i>
                    <h3>No teacher matched your search</h3>
                    <p>Try a different name, username, staff status, or reset the filter.</p>
                </div>
            </div>
        </section>
    </main>

    <button onclick="topFunction()" id="myBtn" title="Go to top">Top</button>

    <script>
    (function () {
        const searchInput = document.getElementById('teacherDirectorySearch');
        const filterButtons = document.querySelectorAll('.teacher-directory-filter');
        const rows = document.querySelectorAll('.teacher-directory-row');
        const resultCount = document.getElementById('teacherDirectoryResultCount');
        const emptyState = document.getElementById('teacherDirectoryEmpty');
        let activeFilter = 'all';

        function applyFilters() {
            const keyword = (searchInput.value || '').toLowerCase().trim();
            let visibleCount = 0;

            rows.forEach(function (row) {
                const searchIndex = (row.getAttribute('data-search') || '').toLowerCase();
                const status = (row.getAttribute('data-status') || '').toLowerCase();
                const matchesKeyword = keyword === '' || searchIndex.indexOf(keyword) !== -1;
                const matchesStatus = activeFilter === 'all' || status === activeFilter;
                const shouldShow = matchesKeyword && matchesStatus;

                row.style.display = shouldShow ? '' : 'none';

                if (shouldShow) {
                    visibleCount++;
                }
            });

            resultCount.textContent = visibleCount + (visibleCount === 1 ? ' teacher shown' : ' teachers shown');
            emptyState.hidden = visibleCount !== 0;
        }

        filterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activeFilter = this.getAttribute('data-filter') || 'all';

                filterButtons.forEach(function (item) {
                    item.classList.remove('is-active');
                });

                this.classList.add('is-active');
                applyFilters();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        applyFilters();
    })();

    var mybutton = document.getElementById("myBtn");

    window.onscroll = function() {scrollFunction();};

    function scrollFunction() {
      if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
        mybutton.style.display = "block";
      } else {
        mybutton.style.display = "none";
      }
    }

    function topFunction() {
      document.body.scrollTop = 0;
      document.documentElement.scrollTop = 0;
    }
    </script>
</body>
</html>
