<?php
session_start();
$_SESSION['Message'] = isset($_SESSION['Message']) ? $_SESSION['Message'] : '';
include("check-login.php");
include("dbstring.php");
include("counselling-utils.php");
ensure_counselling_tables($con);

if(!counselling_is_admin()){
    header("location:".counselling_landing_page());
    exit();
}

if(!function_exists('ca_redirect')){
function ca_redirect(){
    header("location:counsellor-assignment.php");
    exit();
}
}

if(!function_exists('ca_flash_html')){
function ca_flash_html($tone, $message){
    $tone = trim((string)$tone);
    $allowed = array('success', 'error', 'warning', 'info');
    if(!in_array($tone, $allowed, true)){
        $tone = 'info';
    }
    $icon = 'fa-info-circle';
    if($tone === 'success'){
        $icon = 'fa-check-circle';
    }elseif($tone === 'error'){
        $icon = 'fa-exclamation-circle';
    }elseif($tone === 'warning'){
        $icon = 'fa-exclamation-triangle';
    }
    return "<div class='ca-flash ca-flash--".$tone."'><span class='ca-flash__icon'><i class='fa ".$icon."'></i></span><div class='ca-flash__body'>".$message."</div></div>";
}
}

if(isset($_POST['save_class_counsellor'])){
    $_CounsellorId = isset($_POST['counsellorid']) ? trim((string)$_POST['counsellorid']) : '';
    $_ClassId = isset($_POST['classid']) ? trim((string)$_POST['classid']) : '';
    $_BatchId = isset($_POST['batchid']) ? trim((string)$_POST['batchid']) : '';
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';

    if($_CounsellorId === '' || $_ClassId === '' || $_BatchId === ''){
        $_SESSION['Message'] = ca_flash_html('error', 'Select counsellor, class, and batch before saving.');
        ca_redirect();
    }

    $_CounsellorIdEsc = mysqli_real_escape_string($con, $_CounsellorId);
    $_ClassIdEsc = mysqli_real_escape_string($con, $_ClassId);
    $_BatchIdEsc = mysqli_real_escape_string($con, $_BatchId);
    $_RecordedByEsc = mysqli_real_escape_string($con, $_RecordedBy);

    $_Existing = mysqli_query($con, "SELECT assignmentid
        FROM tblcounsellorassignment
        WHERE assignmenttype='class'
          AND classid='$_ClassIdEsc'
          AND batchid='$_BatchIdEsc'
        LIMIT 1");
    if($_Existing && ($row = mysqli_fetch_array($_Existing, MYSQLI_ASSOC))){
        $_AssignmentIdEsc = mysqli_real_escape_string($con, (string)$row['assignmentid']);
        $_Update = mysqli_query($con, "UPDATE tblcounsellorassignment
            SET counsellorid='$_CounsellorIdEsc',
                status='active',
                datetimeentry=NOW(),
                recordedby='$_RecordedByEsc'
            WHERE assignmentid='$_AssignmentIdEsc'");
        $_SESSION['Message'] = $_Update
            ? ca_flash_html('success', 'Class counsellor assignment updated.')
            : ca_flash_html('error', 'Class counsellor assignment could not be updated.');
        ca_redirect();
    }

    include("code.php");
    $_AssignmentIdEsc = mysqli_real_escape_string($con, trim((string)$code));
    $_Insert = mysqli_query($con, "INSERT INTO tblcounsellorassignment(
            assignmentid, counsellorid, assignmenttype, studentid, classid, batchid, status, datetimeentry, recordedby
        ) VALUES(
            '$_AssignmentIdEsc', '$_CounsellorIdEsc', 'class', NULL, '$_ClassIdEsc', '$_BatchIdEsc', 'active', NOW(), '$_RecordedByEsc'
        )");
    $_SESSION['Message'] = $_Insert
        ? ca_flash_html('success', 'Class counsellor assigned.')
        : ca_flash_html('error', 'Class counsellor assignment could not be saved.');
    ca_redirect();
}

if(isset($_POST['save_school_counsellor'])){
    $_CounsellorId = isset($_POST['school_counsellorid']) ? trim((string)$_POST['school_counsellorid']) : '';
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';

    if($_CounsellorId === ''){
        $_SESSION['Message'] = ca_flash_html('error', 'Select the counsellor before saving the school assignment.');
        ca_redirect();
    }

    $_CounsellorIdEsc = mysqli_real_escape_string($con, $_CounsellorId);
    $_RecordedByEsc = mysqli_real_escape_string($con, $_RecordedBy);

    $_Existing = mysqli_query($con, "SELECT assignmentid
        FROM tblcounsellorassignment
        WHERE assignmenttype='school'
        LIMIT 1");
    if($_Existing && ($row = mysqli_fetch_array($_Existing, MYSQLI_ASSOC))){
        $_AssignmentIdEsc = mysqli_real_escape_string($con, (string)$row['assignmentid']);
        $_Update = mysqli_query($con, "UPDATE tblcounsellorassignment
            SET counsellorid='$_CounsellorIdEsc',
                studentid=NULL,
                classid=NULL,
                batchid=NULL,
                status='active',
                datetimeentry=NOW(),
                recordedby='$_RecordedByEsc'
            WHERE assignmentid='$_AssignmentIdEsc'");
        $_SESSION['Message'] = $_Update
            ? ca_flash_html('success', 'School counsellor assignment updated.')
            : ca_flash_html('error', 'School counsellor assignment could not be updated.');
        ca_redirect();
    }

    include("code.php");
    $_AssignmentIdEsc = mysqli_real_escape_string($con, trim((string)$code));
    $_Insert = mysqli_query($con, "INSERT INTO tblcounsellorassignment(
            assignmentid, counsellorid, assignmenttype, studentid, classid, batchid, status, datetimeentry, recordedby
        ) VALUES(
            '$_AssignmentIdEsc', '$_CounsellorIdEsc', 'school', NULL, NULL, NULL, 'active', NOW(), '$_RecordedByEsc'
        )");
    $_SESSION['Message'] = $_Insert
        ? ca_flash_html('success', 'School counsellor assigned.')
        : ca_flash_html('error', 'School counsellor assignment could not be saved.');
    ca_redirect();
}

if(isset($_POST['save_student_counsellor'])){
    $_CounsellorId = isset($_POST['override_counsellorid']) ? trim((string)$_POST['override_counsellorid']) : '';
    $_StudentId = isset($_POST['studentid']) ? trim((string)$_POST['studentid']) : '';
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';

    if($_CounsellorId === '' || $_StudentId === ''){
        $_SESSION['Message'] = ca_flash_html('error', 'Select counsellor and student before saving the override.');
        ca_redirect();
    }

    $_StudentExists = mysqli_query($con, "SELECT userid
        FROM tblsystemuser
        WHERE userid='".mysqli_real_escape_string($con, $_StudentId)."'
          AND systemtype='Student'
        LIMIT 1");
    if(!($_StudentExists && mysqli_num_rows($_StudentExists) > 0)){
        $_SESSION['Message'] = ca_flash_html('error', 'The selected student could not be found.');
        ca_redirect();
    }

    $_CounsellorIdEsc = mysqli_real_escape_string($con, $_CounsellorId);
    $_StudentIdEsc = mysqli_real_escape_string($con, $_StudentId);
    $_RecordedByEsc = mysqli_real_escape_string($con, $_RecordedBy);

    $_Existing = mysqli_query($con, "SELECT assignmentid
        FROM tblcounsellorassignment
        WHERE assignmenttype='student'
          AND studentid='$_StudentIdEsc'
        LIMIT 1");
    if($_Existing && ($row = mysqli_fetch_array($_Existing, MYSQLI_ASSOC))){
        $_AssignmentIdEsc = mysqli_real_escape_string($con, (string)$row['assignmentid']);
        $_Update = mysqli_query($con, "UPDATE tblcounsellorassignment
            SET counsellorid='$_CounsellorIdEsc',
                status='active',
                datetimeentry=NOW(),
                recordedby='$_RecordedByEsc'
            WHERE assignmentid='$_AssignmentIdEsc'");
        $_SESSION['Message'] = $_Update
            ? ca_flash_html('success', 'Student counsellor override updated.')
            : ca_flash_html('error', 'Student counsellor override could not be updated.');
        ca_redirect();
    }

    include("code.php");
    $_AssignmentIdEsc = mysqli_real_escape_string($con, trim((string)$code));
    $_Insert = mysqli_query($con, "INSERT INTO tblcounsellorassignment(
            assignmentid, counsellorid, assignmenttype, studentid, classid, batchid, status, datetimeentry, recordedby
        ) VALUES(
            '$_AssignmentIdEsc', '$_CounsellorIdEsc', 'student', '$_StudentIdEsc', NULL, NULL, 'active', NOW(), '$_RecordedByEsc'
        )");
    $_SESSION['Message'] = $_Insert
        ? ca_flash_html('success', 'Student counsellor override saved.')
        : ca_flash_html('error', 'Student counsellor override could not be saved.');
    ca_redirect();
}

if(isset($_GET['deactivate_assignment'])){
    $_AssignmentId = trim((string)$_GET['deactivate_assignment']);
    if($_AssignmentId !== ''){
        $_AssignmentIdEsc = mysqli_real_escape_string($con, $_AssignmentId);
        $_Update = mysqli_query($con, "UPDATE tblcounsellorassignment
            SET status='inactive'
            WHERE assignmentid='$_AssignmentIdEsc'");
        $_SESSION['Message'] = $_Update
            ? ca_flash_html('warning', 'Counsellor assignment deactivated.')
            : ca_flash_html('error', 'Counsellor assignment could not be deactivated.');
    }
    ca_redirect();
}

if(isset($_GET['delete_assignment'])){
    $_AssignmentId = trim((string)$_GET['delete_assignment']);
    if($_AssignmentId !== ''){
        $_AssignmentIdEsc = mysqli_real_escape_string($con, $_AssignmentId);
        $_Delete = mysqli_query($con, "DELETE FROM tblcounsellorassignment WHERE assignmentid='$_AssignmentIdEsc'");
        $_SESSION['Message'] = $_Delete
            ? ca_flash_html('warning', 'Counsellor assignment deleted.')
            : ca_flash_html('error', 'Counsellor assignment could not be deleted.');
    }
    ca_redirect();
}

$_TeacherOptions = array();
$_TeachersSql = mysqli_query($con, "SELECT userid, firstname, surname, othernames
    FROM tblsystemuser
    WHERE systemtype='Teacher'
      AND status='active'
    ORDER BY firstname ASC, surname ASC, othernames ASC");
if($_TeachersSql){
    while($row = mysqli_fetch_array($_TeachersSql, MYSQLI_ASSOC)){
        $_TeacherOptions[] = $row;
    }
}

$_ClassOptions = array();
$_ClassSql = mysqli_query($con, "SELECT class_entryid, class_name
    FROM tblclassentry
    ORDER BY class_name ASC");
if($_ClassSql){
    while($row = mysqli_fetch_array($_ClassSql, MYSQLI_ASSOC)){
        $_ClassOptions[] = $row;
    }
}

$_BatchOptions = array();
$_BatchSql = mysqli_query($con, "SELECT batchid, batch
    FROM tblbatch
    WHERE status='active'
    ORDER BY datetimeentry DESC, batch DESC");
if($_BatchSql){
    while($row = mysqli_fetch_array($_BatchSql, MYSQLI_ASSOC)){
        $_BatchOptions[] = $row;
    }
}

$_SchoolAssignmentRow = counselling_school_assignment_row($con);
$_CurrentSchoolCounsellorId = $_SchoolAssignmentRow ? trim((string)$_SchoolAssignmentRow['counsellorid']) : '';

$_StudentOptions = array();
$_StudentSql = mysqli_query($con, "SELECT userid, firstname, surname, othernames
    FROM tblsystemuser
    WHERE systemtype='Student'
      AND status='active'
    ORDER BY firstname ASC, surname ASC, othernames ASC");
if($_StudentSql){
    while($row = mysqli_fetch_array($_StudentSql, MYSQLI_ASSOC)){
        $_StudentOptions[] = $row;
    }
}

$_AssignmentRows = counselling_assignment_rows($con);
$_ActiveAssignments = 0;
$_InactiveAssignments = 0;
$_SchoolScopeCount = 0;
$_ClassScopeCount = 0;
$_StudentScopeCount = 0;
$_CounsellorMap = array();
foreach($_AssignmentRows as $_AssignmentRow){
    $_CounsellorMap[(string)$_AssignmentRow['counsellorid']] = true;
    if(trim((string)$_AssignmentRow['status']) === 'active'){
        $_ActiveAssignments++;
    }else{
        $_InactiveAssignments++;
    }
    if(trim((string)$_AssignmentRow['assignmenttype']) === 'student'){
        $_StudentScopeCount++;
    }elseif(trim((string)$_AssignmentRow['assignmenttype']) === 'school'){
        $_SchoolScopeCount++;
    }else{
        $_ClassScopeCount++;
    }
}
$_AssignedCounsellorCount = count($_CounsellorMap);
$_FlashHtml = $_SESSION['Message'];
$_SESSION['Message'] = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/counsellor-assignment.css">
<script src="scripts/counsellor-assignment.js" defer></script>
</head>
<body class="counsellor-assignment-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <main class="ca-shell">
        <section class="ca-hero">
            <div class="ca-hero__copy">
                <span class="ca-kicker"><i class="fa fa-heartbeat"></i> Guidance &amp; Counselling</span>
                <h1>Assign Dedicated Counsellors</h1>
                <p>Set one counsellor for the whole school, add class-level routing where needed, and keep student-specific overrides for exceptional cases.</p>
                <div class="ca-chip-row">
                    <span class="ca-chip"><i class="fa fa-users"></i> Teachers: <?php echo number_format((int)count($_TeacherOptions)); ?></span>
                    <span class="ca-chip"><i class="fa fa-graduation-cap"></i> Students: <?php echo number_format((int)count($_StudentOptions)); ?></span>
                    <span class="ca-chip"><i class="fa fa-sitemap"></i> Counsellors Assigned: <?php echo number_format((int)$_AssignedCounsellorCount); ?></span>
                    <span class="ca-chip"><i class="fa fa-university"></i> School Scope: <?php echo number_format((int)$_SchoolScopeCount); ?></span>
                </div>
            </div>
            <div class="ca-stats">
                <article class="ca-stat">
                    <span>Total Assignments</span>
                    <strong><?php echo number_format((int)count($_AssignmentRows)); ?></strong>
                    <small>School, class, and student assignments on file.</small>
                </article>
                <article class="ca-stat">
                    <span>Active Assignments</span>
                    <strong><?php echo number_format((int)$_ActiveAssignments); ?></strong>
                    <small>Assignments currently used for new counselling requests.</small>
                </article>
                <article class="ca-stat">
                    <span>School Scope</span>
                    <strong><?php echo number_format((int)$_SchoolScopeCount); ?></strong>
                    <small>The counsellor covering the whole school.</small>
                </article>
                <article class="ca-stat">
                    <span>Class Scopes</span>
                    <strong><?php echo number_format((int)$_ClassScopeCount); ?></strong>
                    <small>Extra class routing beyond the school-wide setting.</small>
                </article>
                <article class="ca-stat">
                    <span>Student Overrides</span>
                    <strong><?php echo number_format((int)$_StudentScopeCount); ?></strong>
                    <small>Student-specific counsellor changes.</small>
                </article>
            </div>
        </section>

        <?php if($_FlashHtml !== ''){ ?>
        <div class="ca-message-stack"><?php echo $_FlashHtml; ?></div>
        <?php } ?>

        <div class="ca-layout">
            <aside class="ca-sidebar">
                <section class="ca-surface">
                    <div class="ca-panel-head">
                        <div>
                            <span class="ca-panel-kicker">School Assignment</span>
                            <h2>Assign One Counsellor To The Whole School</h2>
                            <p>Every student in the school will route to this counsellor unless a class or student override is set.</p>
                        </div>
                    </div>
                    <?php if($_SchoolAssignmentRow){ ?>
                    <p><strong>Current School Counsellor:</strong> <?php echo counselling_esc((string)$_SchoolAssignmentRow['counsellor_name']); ?></p>
                    <?php } ?>
                    <form method="post" action="counsellor-assignment.php" class="ca-form">
                        <label class="ca-field">
                            <span>Counsellor</span>
                            <select name="school_counsellorid" required>
                                <option value="">Select Counsellor</option>
                                <?php foreach($_TeacherOptions as $_TeacherRow){ ?>
                                <option value="<?php echo counselling_esc((string)$_TeacherRow['userid']); ?>" <?php echo trim((string)$_TeacherRow['userid']) === $_CurrentSchoolCounsellorId ? 'selected' : ''; ?>><?php echo counselling_esc(counselling_person_name($_TeacherRow)); ?> (<?php echo counselling_esc((string)$_TeacherRow['userid']); ?>)</option>
                                <?php } ?>
                            </select>
                        </label>
                        <button class="ca-btn ca-btn--primary" type="submit" name="save_school_counsellor"><i class="fa fa-save"></i> Save School Assignment</button>
                    </form>
                </section>

                <section class="ca-surface">
                    <div class="ca-panel-head">
                        <div>
                            <span class="ca-panel-kicker">Class Assignment</span>
                            <h2>Assign By Class</h2>
                            <p>Use this only when a class should follow a different counsellor from the school-wide assignment.</p>
                        </div>
                    </div>
                    <form method="post" action="counsellor-assignment.php" class="ca-form">
                        <label class="ca-field">
                            <span>Counsellor</span>
                            <select name="counsellorid" required>
                                <option value="">Select Counsellor</option>
                                <?php foreach($_TeacherOptions as $_TeacherRow){ ?>
                                <option value="<?php echo counselling_esc((string)$_TeacherRow['userid']); ?>"><?php echo counselling_esc(counselling_person_name($_TeacherRow)); ?> (<?php echo counselling_esc((string)$_TeacherRow['userid']); ?>)</option>
                                <?php } ?>
                            </select>
                        </label>
                        <label class="ca-field">
                            <span>Class</span>
                            <select name="classid" required>
                                <option value="">Select Class</option>
                                <?php foreach($_ClassOptions as $_ClassRow){ ?>
                                <option value="<?php echo counselling_esc((string)$_ClassRow['class_entryid']); ?>"><?php echo counselling_esc((string)$_ClassRow['class_name']); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <label class="ca-field">
                            <span>Batch</span>
                            <select name="batchid" required>
                                <option value="">Select Batch</option>
                                <?php foreach($_BatchOptions as $_BatchRow){ ?>
                                <option value="<?php echo counselling_esc((string)$_BatchRow['batchid']); ?>"><?php echo counselling_esc((string)$_BatchRow['batch']); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <button class="ca-btn ca-btn--primary" type="submit" name="save_class_counsellor"><i class="fa fa-save"></i> Save Class Assignment</button>
                    </form>
                </section>

                <section class="ca-surface">
                    <div class="ca-panel-head">
                        <div>
                            <span class="ca-panel-kicker">Student Override</span>
                            <h2>Assign A Different Counsellor</h2>
                            <p>Use this when one student should not follow the school or class counsellor already in place.</p>
                        </div>
                    </div>
                    <form method="post" action="counsellor-assignment.php" class="ca-form">
                        <label class="ca-field">
                            <span>Counsellor</span>
                            <select name="override_counsellorid" required>
                                <option value="">Select Counsellor</option>
                                <?php foreach($_TeacherOptions as $_TeacherRow){ ?>
                                <option value="<?php echo counselling_esc((string)$_TeacherRow['userid']); ?>"><?php echo counselling_esc(counselling_person_name($_TeacherRow)); ?> (<?php echo counselling_esc((string)$_TeacherRow['userid']); ?>)</option>
                                <?php } ?>
                            </select>
                        </label>
                        <label class="ca-field">
                            <span>Student ID</span>
                            <input type="text" name="studentid" list="ca-student-list" placeholder="Enter or search student ID" required>
                            <datalist id="ca-student-list">
                                <?php foreach($_StudentOptions as $_StudentRow){ ?>
                                <option value="<?php echo counselling_esc((string)$_StudentRow['userid']); ?>"><?php echo counselling_esc(counselling_person_name($_StudentRow)); ?></option>
                                <?php } ?>
                            </datalist>
                        </label>
                        <button class="ca-btn ca-btn--primary" type="submit" name="save_student_counsellor"><i class="fa fa-save"></i> Save Student Override</button>
                    </form>
                </section>
            </aside>

            <section class="ca-main">
                <section class="ca-surface">
                    <div class="ca-panel-head">
                        <div>
                            <span class="ca-panel-kicker">Assignment List</span>
                            <h2>Current Counsellor Assignments</h2>
                            <p>Review every school-wide, class-based, and student-specific counselling assignment from one list.</p>
                        </div>
                    </div>

                    <?php if(empty($_AssignmentRows)){ ?>
                    <div class="ca-empty-state">
                        <h3>No counsellor assignments yet</h3>
                        <p>Start by assigning the school counsellor, then add class or student overrides only where needed.</p>
                    </div>
                    <?php } else { ?>
                    <div class="ca-table-wrap">
                        <table class="ca-table">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>Counsellor</th>
                                    <th>Scope</th>
                                    <th>Target</th>
                                    <th>Status</th>
                                    <th>Date / Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($_AssignmentRows as $_AssignmentRow){ ?>
                                <?php
                                $_AssignmentType = trim((string)$_AssignmentRow['assignmenttype']);
                                if($_AssignmentType === 'school'){
                                    $_ScopeType = 'School Scope';
                                }elseif($_AssignmentType === 'student'){
                                    $_ScopeType = 'Student Override';
                                }else{
                                    $_ScopeType = 'Class Scope';
                                }
                                $_TargetLabel = counselling_scope_label($_AssignmentRow);
                                $_StatusMeta = counselling_status_meta($_AssignmentRow['status']);
                                ?>
                                <tr>
                                    <td data-label="Actions">
                                        <div class="ca-action-row">
                                            <a class="ca-table-link" href="counsellor-assignment.php?deactivate_assignment=<?php echo rawurlencode((string)$_AssignmentRow['assignmentid']); ?>" onclick="return confirm('Deactivate this counsellor assignment?');"><i class="fa fa-ban"></i> Deactivate</a>
                                            <a class="ca-table-link ca-table-link--danger" href="counsellor-assignment.php?delete_assignment=<?php echo rawurlencode((string)$_AssignmentRow['assignmentid']); ?>" onclick="return confirm('Delete this counsellor assignment?');"><i class="fa fa-trash"></i> Delete</a>
                                        </div>
                                    </td>
                                    <td data-label="Counsellor">
                                        <strong><?php echo counselling_esc((string)$_AssignmentRow['counsellor_name']); ?></strong>
                                        <small><?php echo counselling_esc((string)$_AssignmentRow['counsellorid']); ?></small>
                                    </td>
                                    <td data-label="Scope"><?php echo counselling_esc($_ScopeType); ?></td>
                                    <td data-label="Target"><?php echo counselling_esc($_TargetLabel); ?></td>
                                    <td data-label="Status"><span class="ca-status ca-status--<?php echo counselling_esc($_StatusMeta['class']); ?>"><?php echo counselling_esc($_StatusMeta['label']); ?></span></td>
                                    <td data-label="Date / Time"><?php echo counselling_esc(counselling_format_datetime($_AssignmentRow['datetimeentry'])); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </section>
            </section>
        </div>
    </main>
</div>
</body>
</html>
