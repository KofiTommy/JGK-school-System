<?php
include("check-login.php");
include_once("student-chat-utils.php");
$_ShowHouseMasterLinks = false;
$_ShowSeniorHouseLinks = false;
$_HomeLink = "index.php";
$_StudentChatEnabledForMenu = student_chat_is_enabled($con);
if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
   $_SESSION['ACCESSLEVEL'] === "user" &&
   $_SESSION['SYSTEMTYPE'] === "Teacher"){
    include_once("house-master-utils.php");
    include_once("class-teacher-utils.php");
    include_once("user-management-utils.php");
    ensure_house_tables($con);
    ensure_class_teacher_table($con);
    $_HouseMasterSummary = house_master_get_teacher_role_summary($con, $_SESSION['USERID']);
    $_ShowHouseMasterLinks = !empty($_HouseMasterSummary['has_house_assignment']);
    $_ShowSeniorHouseLinks = !empty($_HouseMasterSummary['has_senior_assignment']);
    $_HouseMasterDashboardLabel = isset($_HouseMasterSummary['dashboard_label']) ? $_HouseMasterSummary['dashboard_label'] : "House Master Dashboard";
    $_TeacherExtraAccessLinks = um_teacher_extra_nav_links($con, $_SESSION['USERID']);
    $_ShowTeacherAttendanceLinks = class_teacher_has_any_assignment($con, $_SESSION['USERID']);
}
if(!isset($_HouseMasterDashboardLabel)){
    $_HouseMasterDashboardLabel = "House Master Dashboard";
}
if(!isset($_TeacherExtraAccessLinks)){
    $_TeacherExtraAccessLinks = array();
}
if(!isset($_ShowTeacherAttendanceLinks)){
    $_ShowTeacherAttendanceLinks = false;
}
$_ShowExeatManagerLinks = $_ShowHouseMasterLinks || $_ShowSeniorHouseLinks;
if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'])){
    if($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "User"){
        $_HomeLink = "user.php";
    }
    elseif($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Teacher"){
        $_HomeLink = "teacher-page.php";
    }
    elseif($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Student"){
        $_HomeLink = "student-page.php";
    }
    elseif($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Headmaster"){
        $_HomeLink = "headmaster-page.php";
    }
    elseif($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "AssistantHeadAcademic"){
        $_HomeLink = "assistant-head-academics-page.php";
    }
    elseif($_SESSION['ACCESSLEVEL'] === "administrator" && $_SESSION['SYSTEMTYPE'] === "normal_user"){
        $_HomeLink = "admin.php";
    }
    elseif($_SESSION['ACCESSLEVEL'] === "administrator" && $_SESSION['SYSTEMTYPE'] === "super_user"){
        $_HomeLink = "super.php";
    }
}
$_ShowLiveUsersIndicator = false;
$_LiveUsersCount = 0;
if(isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL'] === "administrator"){
    $_ShowLiveUsersIndicator = true;
    if(function_exists('um_count_live_users')){
        $_LiveUsersCount = um_count_live_users($con, 5);
    }
}
?>
<html>

<style>

button{
	background-color: transparent;
	border:0px solid gray;
	padding: 10px;
	cursor: pointer;
    font-size: 10px;
    font-family: sans-serif;

}
.top-action-link,
.dropbtnNew {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 44px;
    padding: 10px 14px;
    border: 1px solid #d6dde6;
    border-radius: 8px;
    background-color: #ffffff;
    color: #222;
    font-size: 14px;
    line-height: 1.2;
    text-decoration: none;
    cursor: pointer;
    touch-action: manipulation;
    box-sizing: border-box;
    white-space: nowrap;
}

.top-action-link:hover,
.dropbtnNew:hover,
.dropbtnNew:focus,
.dropdownNew.is-open .dropbtnNew {
    background-color: #f5f7fa;
}

.top-action-link i,
.dropbtnNew i {
    font-size: 16px;
}

.logout-link i {
    color: red;
}

.top-action-indicator{
    cursor: default;
    background: linear-gradient(135deg, #edf7ff, #f7fbff);
    border-color: #c8dced;
    color: #163a5c;
    font-weight: 700;
}

.top-action-indicator i{
    color: #0b63ce;
}

.top-action-indicator small{
    color: #607b95;
    font-size: 11px;
    font-weight: 700;
}

.dropdownNew {
    position: relative;
    display: flex;
    align-items: stretch;
    flex: 0 0 auto;
    z-index: 999990;
}

.header,
.top-actions {
    position: relative;
    z-index: 999980;
    overflow: visible !important;
}

.dropdownNew .dropbtnNew {
    width: 100%;
    appearance: none;
    -webkit-appearance: none;
    min-height: 44px;
}

.dropdownNew-content {
    display: none;
    position: absolute;
    top: 100%;
    background-color: #f9f9f9;
    min-width: 220px;
    max-width: min(90vw, 320px);
    border: 1px solid #d6dde6;
    border-radius: 10px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.3);
    right: 0;
    z-index: 999999;
    padding-top: 6px;
    overflow: visible;

}

.dropdownNew-content a {
    color: black;
    padding: 0;
    text-decoration: none;
    display: block;

}

.dropdownNew-content a:hover {
    background-color: #f1f5f9;
}

.dropdownNew-content button {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    font-size: 13px;
    text-align: left;
    pointer-events: none;
}

.dropdownNew.is-open .dropdownNew-content {
    display: block;
}

@media (hover:hover) {
    .dropdownNew:hover .dropdownNew-content {
        display: block;
    }

    .dropdownNew:hover .dropbtnNew {
        background-color: #f5f7fa;
    }
}
</style>
<style type="text/css">
.top-actions {
  width: 100%;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

@media (max-width:640px) {
  .top-actions {
    justify-content: flex-start;
    position: relative;
    z-index: 999980;
  }

  .top-action-link,
  .dropbtnNew,
  .dropdownNew {
    flex: 1 1 auto;
  }

  .dropdownNew-content {
    left: 0;
    right: 0;
    min-width: 100%;
    max-width: none;
  }

  .dropdownNew.is-open .dropdownNew-content {
    position: fixed;
    top: 72px;
    left: 10px;
    right: 10px;
    width: auto;
    min-width: 0;
    max-width: none;
    max-height: calc(100vh - 88px);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    z-index: 2147483000;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    box-shadow: 0 22px 48px rgba(15, 23, 42, 0.28);
  }
}
</style>
</head>
<body>
	<div style="padding:2px;" align="right" class="top-actions">

    <a class="top-action-link" href="<?php echo $_HomeLink; ?>" title="Click To Home Page">
      <i class="fa fa-home"></i> Home
    </a>
    <?php if($_ShowLiveUsersIndicator){ ?>
    <span class="top-action-link top-action-indicator" id="top-live-users-indicator" title="Users active in the last 5 minutes">
      <i class="fa fa-signal"></i> Live Users: <strong id="top-live-users-count"><?php echo number_format((int)$_LiveUsersCount); ?></strong>
    </span>
    <?php } ?>
    <a class="top-action-link logout-link" href="logout.php" title="Click To Log Out">
      <i class="fa fa-power-off"></i> Logout
    </a>
	<div class="dropdownNew" data-menu-dropdown>
  <button type="button" class="dropbtnNew" aria-expanded="false" aria-haspopup="true" aria-controls="account-actions-menu">
    <i class="fa fa-bars"></i> Menu
  </button>
  <div class="dropdownNew-content" id="account-actions-menu">
  
    <?php
//include("check-login.php");
//session_start();
if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="User")
{
  ?>
<div id="admin" align="left" style="margin-top:5px;">
<a href="edit-account.php"><button><i class="fa fa-user" style="color:brown"></i> Edit Profile</button></a>
<a href="viewusers.php"><button><i class="fa fa-user" style="color:royalblue"></i> View Teachers</button></a>
<a href="viewstudents.php"><button><i class="fa fa-user" style="color:royalblue"></i> View Students</button></a>
<a href="register-student.php"><button><i class="fa fa-user" style="color:royalblue"></i> Register Student</button></a>
<a href="register-teacher.php"><button><i class="fa fa-user" style="color:royalblue"></i> Register Teacher</button></a>

<a href="upload-register.php"><button><i class="fa fa-upload" style="color:blue"></i> Upload Registers</button></a>
<a href="logout.php"><button><i class="fa fa-power-off" style="color:red"></i> Logout </button></a>
</div>
<?php
}
elseif($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Teacher"){
 ?>
<div id="admin" align="left" style="margin-top:5px;">
<a href="edit-account.php"><button><i class="fa fa-user" style="color:brown"></i> Edit Profile</button></a>
<?php
if($_ShowHouseMasterLinks){
?>
<a href="house-master-dashboard.php"><button><i class="fa fa-home" style="color:teal"></i> <?php echo htmlspecialchars($_HouseMasterDashboardLabel, ENT_QUOTES, "UTF-8"); ?></button></a>
<?php
}
if($_ShowExeatManagerLinks){
?>
<a href="house-master-exeat.php"><button><i class="fa fa-check" style="color:teal"></i> House Exeat Management</button></a>
<?php
}
if($_ShowSeniorHouseLinks){
?>
<a href="senior-house-dashboard.php"><button><i class="fa fa-dashboard" style="color:teal"></i> Senior House Dashboard</button></a>
<?php
}
?>
<a href="lesson-timetable-report.php"><button><i class="fa fa-calendar" style="color:#0f766e"></i> Lesson Timetable</button></a>
<a href="teacher-course-registration.php"><button><i class="fa fa-list-alt" style="color:#1d4ed8"></i> Course Registration</button></a>
<a href="online-voting.php"><button><i class="fa fa-trophy" style="color:#d97706"></i> Online Voting</button></a>
<?php if($_ShowTeacherAttendanceLinks){ ?>
<a href="student-attendance.php"><button><i class="fa fa-check-square-o" style="color:#0f766e"></i> Student Attendance</button></a>
<a href="student-attendance-report.php"><button><i class="fa fa-bar-chart" style="color:#0f766e"></i> Attendance Summary</button></a>
<?php } ?>
<?php
if(!empty($_TeacherExtraAccessLinks)){
    foreach($_TeacherExtraAccessLinks as $_ExtraLink){
?>
<a href="<?php echo htmlspecialchars($_ExtraLink['href'], ENT_QUOTES, 'UTF-8'); ?>"><button><i class="fa <?php echo htmlspecialchars($_ExtraLink['icon'], ENT_QUOTES, 'UTF-8'); ?>" style="color:#1d4ed8"></i> <?php echo htmlspecialchars($_ExtraLink['label'], ENT_QUOTES, 'UTF-8'); ?></button></a>
<?php
    }
}
?>
<a href="logout.php"><button><i class="fa fa-power-off" style="color:red"></i> Logout </button></a>
</div>
<?php
}

elseif($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Student"){
 ?>
<div id="admin" align="left" style="margin-top:5px;">
<a href="edit-account.php"><button><i class="fa fa-user" style="color:brown"></i> Edit Profile</button></a>
<a href="individual-terminal-report.php"><button><i class="fa fa-book" style="color:#2563eb"></i> Terminal Report</button></a>
<a href="account-statements.php"><button><i class="fa fa-money" style="color:#16a34a"></i> Account Statement</button></a>
<a href="examinationtimetablereport.php"><button><i class="fa fa-calendar" style="color:#7c3aed"></i> Exam Timetable</button></a>
<a href="lesson-timetable-report.php"><button><i class="fa fa-clock-o" style="color:#0f766e"></i> Lesson Timetable</button></a>
<a href="student-course-registration.php"><button><i class="fa fa-list-alt" style="color:#1d4ed8"></i> Course Registration</button></a>
<a href="student-attendance-report.php"><button><i class="fa fa-bar-chart" style="color:#0f766e"></i> My Attendance</button></a>
<a href="online-voting.php"><button><i class="fa fa-trophy" style="color:#d97706"></i> Online Voting</button></a>
<a href="messages.php"><button><i class="fa fa-comments" style="color:#ea580c"></i> Message Box</button></a>
<?php if($_StudentChatEnabledForMenu){ ?>
<a href="student-chat.php"><button><i class="fa fa-user-plus" style="color:#0f766e"></i> Student Chat</button></a>
<?php } ?>
<a href="student-exeat-request.php"><button><i class="fa fa-file" style="color:teal"></i> Request Exeat</button></a>
<a href="logout.php"><button><i class="fa fa-power-off" style="color:red"></i> Logout </button></a>
</div>
<?php
}

elseif($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Headmaster"){
 ?>
<div id="admin" align="left" style="margin-top:5px;">
<a href="edit-account.php"><button><i class="fa fa-user" style="color:brown"></i> Edit Profile</button></a>
<a href="student-history.php"><button><i class="fa fa-history" style="color:#0f766e"></i> Student Transcript</button></a>
<a href="viewstudents.php"><button><i class="fa fa-graduation-cap" style="color:#1d4ed8"></i> View Students</button></a>
<a href="continuing-students.php"><button><i class="fa fa-users" style="color:#2563eb"></i> Continuing Students</button></a>
<a href="viewusers.php"><button><i class="fa fa-users" style="color:#1d4ed8"></i> Teachers List</button></a>
<a href="duty-roster.php"><button><i class="fa fa-calendar-check-o" style="color:#0f766e"></i> Teacher On Duty</button></a>
<a href="senior-house-dashboard.php"><button><i class="fa fa-shield" style="color:#0f766e"></i> Senior House Overview</button></a>
<a href="student-attendance-report.php"><button><i class="fa fa-bar-chart" style="color:#0f766e"></i> Attendance Summary</button></a>
<a href="terminal-report.php"><button><i class="fa fa-file-text-o" style="color:#d97706"></i> Examination Report</button></a>
<a href="internal-exam-analysis.php"><button><i class="fa fa-bar-chart" style="color:#0f766e"></i> Internal Exams Analysis</button></a>
<a href="waec-analysis.php"><button><i class="fa fa-line-chart" style="color:#1d4ed8"></i> WAEC Analysis</button></a>
<a href="lesson-timetable-report.php"><button><i class="fa fa-calendar" style="color:#0f766e"></i> Lesson Timetable</button></a>
<a href="online-admission-admin.php"><button><i class="fa fa-globe" style="color:#0ea5e9"></i> Online Admission</button></a>
<a href="messages.php"><button><i class="fa fa-comments" style="color:#ea580c"></i> Message Box</button></a>
<a href="notification.php"><button><i class="fa fa-bullhorn" style="color:#b45309"></i> Send Notification</button></a>
<a href="logout.php"><button><i class="fa fa-power-off" style="color:red"></i> Logout </button></a>
</div>
<?php
}

elseif($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="AssistantHeadAcademic"){
 ?>
<div id="admin" align="left" style="margin-top:5px;">
<a href="edit-account.php"><button><i class="fa fa-user" style="color:brown"></i> Edit Profile</button></a>
<a href="student-history.php"><button><i class="fa fa-history" style="color:#0f766e"></i> Student Transcript</button></a>
<a href="continuing-students.php"><button><i class="fa fa-users" style="color:#2563eb"></i> Continuing Students</button></a>
<a href="promotion-center.php"><button><i class="fa fa-level-up" style="color:#2563eb"></i> Promotion Center</button></a>
<a href="view-class-registry.php"><button><i class="fa fa-folder-open" style="color:#1d4ed8"></i> View Class Registry</button></a>
<a href="subject-classification.php"><button><i class="fa fa-book" style="color:#0f766e"></i> Subject Classification</button></a>
<a href="subject-assignment.php"><button><i class="fa fa-plus" style="color:#1d4ed8"></i> Subject Assignment</button></a>
<a href="view-all-subject-assigned.php"><button><i class="fa fa-book" style="color:#0f766e"></i> Assigned Subjects</button></a>
<a href="class-teacher-assignment.php"><button><i class="fa fa-users" style="color:#0f766e"></i> Class Teacher Assignment</button></a>
<a href="student-attendance-report.php"><button><i class="fa fa-bar-chart" style="color:#0f766e"></i> Attendance Summary</button></a>
<a href="terminal-report.php"><button><i class="fa fa-file-text-o" style="color:#d97706"></i> Examination Report</button></a>
<a href="report-approval-board.php"><button><i class="fa fa-check-circle" style="color:#0f766e"></i> Report Approval</button></a>
<a href="internal-exam-analysis.php"><button><i class="fa fa-bar-chart" style="color:#0f766e"></i> Internal Exams Analysis</button></a>
<a href="waec-analysis.php"><button><i class="fa fa-line-chart" style="color:#1d4ed8"></i> WAEC Analysis</button></a>
<a href="lesson-timetable-report.php"><button><i class="fa fa-calendar" style="color:#0f766e"></i> Lesson Timetable</button></a>
<a href="examinationtimetablereport.php"><button><i class="fa fa-book" style="color:#7c3aed"></i> Exam Time Table Report</button></a>
<a href="course-registration-admin.php"><button><i class="fa fa-list-alt" style="color:#1d4ed8"></i> Course Registration</button></a>
<a href="messages.php"><button><i class="fa fa-comments" style="color:#ea580c"></i> Message Box</button></a>
<a href="notification.php"><button><i class="fa fa-bullhorn" style="color:#b45309"></i> Send Notification</button></a>
<a href="logout.php"><button><i class="fa fa-power-off" style="color:red"></i> Logout </button></a>
</div>
<?php
}

elseif($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="normal_user"){
 ?>
<div id="admin" align="left" style="margin-top:5px;">
<a href="edit-account.php"><button><i class="fa fa-user" style="color:brown"></i> Edit Profile</button></a>
<a href="user-management.php"><button><i class="fa fa-users" style="color:#1d4ed8"></i> User Management</button></a>
<a href="user-visit-history.php"><button><i class="fa fa-clock-o" style="color:#0f766e"></i> User Visit History</button></a>
<a href="register-student.php"><button><i class="fa fa-user" style="color:royalblue"></i> Register Student</button></a>
<a href="register-teacher.php"><button><i class="fa fa-user" style="color:royalblue"></i> Register Teacher</button></a>
<a href="class-teacher-assignment.php"><button><i class="fa fa-plus" style="color:teal"></i> Class Teacher Assignment</button></a>
<a href="student-attendance.php"><button><i class="fa fa-check-square-o" style="color:#0f766e"></i> Student Attendance</button></a>
<a href="student-attendance-report.php"><button><i class="fa fa-bar-chart" style="color:#0f766e"></i> Attendance Summary</button></a>
<a href="duty-roster.php"><button><i class="fa fa-calendar-check-o" style="color:#0f766e"></i> Duty Roster</button></a>
<a href="lesson-timetable.php"><button><i class="fa fa-calendar" style="color:#0f766e"></i> Lesson Timetable Entry</button></a>
<a href="lesson-timetable-report.php"><button><i class="fa fa-book" style="color:#0f766e"></i> Lesson Timetable Report</button></a>
<a href="course-registration-admin.php"><button><i class="fa fa-list-alt" style="color:#1d4ed8"></i> Course Registration</button></a>
<a href="report-approval-board.php"><button><i class="fa fa-check-circle" style="color:#0f766e"></i> Report Approval</button></a>
<a href="online-admission-admin.php"><button><i class="fa fa-globe" style="color:#0ea5e9"></i> Online Admission</button></a>
<a href="online-voting-admin.php"><button><i class="fa fa-trophy" style="color:#d97706"></i> Online Voting</button></a>
<a href="student-chat-monitor.php"><button><i class="fa fa-eye" style="color:#0f766e"></i> Student Chat Monitor</button></a>
<a href="student-chat-settings.php"><button><i class="fa fa-sliders" style="color:#0f766e"></i> Student Chat Control</button></a>
<a href="payments.php"><button><i class="fa fa-credit-card" style="color:#16a34a"></i> Payments</button></a>
<a href="teacher-billing-assignment.php"><button><i class="fa fa-users" style="color:#16a34a"></i> Teacher Billing Assignment</button></a>
<a href="house-entry.php"><button><i class="fa fa-home" style="color:teal"></i> House Entry</button></a>
<a href="house-master-assignment.php"><button><i class="fa fa-plus" style="color:teal"></i> House Master Assignment</button></a>
<a href="student-house-assignment.php"><button><i class="fa fa-users" style="color:teal"></i> Student House Assignment</button></a>
<a href="senior-house-assignment.php"><button><i class="fa fa-star" style="color:teal"></i> Senior House Assignment</button></a>
<a href="senior-house-dashboard.php"><button><i class="fa fa-dashboard" style="color:teal"></i> Senior House Dashboard</button></a>


<a href="upload-register.php"><button><i class="fa fa-upload" style="color:blue"></i> Upload Registers</button></a>
<a href="waec-analysis.php"><button><i class="fa fa-line-chart" style="color:teal"></i> WAEC Analysis</button></a>

<a href="viewusers.php"><button><i class="fa fa-user" style="color:royalblue"></i> View Teachers</button></a>
<a href="viewstudents.php"><button><i class="fa fa-user" style="color:royalblue"></i> View Students</button></a>
<!--<a href="backup_db.php"><button class="btn-menu"><i class="fa fa-book" style="color:royalblue"></i> Backup </button></a>-->
<a href="logout.php"><button><i class="fa fa-power-off" style="color:red"></i> Logout </button></a>
</div>
<?php
}
elseif($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="super_user"){
 ?>
<div id="admin" align="left" style="margin-top:5px;">
<a href="edit-account.php"><button><i class="fa fa-user" style="color:brown"></i> Edit Profile</button></a>
<a href="user-management.php"><button ><i class="fa fa-users" style="color:#1d4ed8"></i> User Management</button></a>
<a href="user-visit-history.php"><button><i class="fa fa-clock-o" style="color:#0f766e"></i> User Visit History</button></a>
<a href="register-student.php"><button><i class="fa fa-user" style="color:royalblue"></i> Register Student</button></a>
<a href="register-teacher.php"><button><i class="fa fa-user" style="color:royalblue"></i> Register Teacher</button></a>
<a href="class-teacher-assignment.php"><button><i class="fa fa-plus" style="color:teal"></i> Class Teacher Assignment</button></a>
<a href="student-attendance.php"><button><i class="fa fa-check-square-o" style="color:#0f766e"></i> Student Attendance</button></a>
<a href="student-attendance-report.php"><button><i class="fa fa-bar-chart" style="color:#0f766e"></i> Attendance Summary</button></a>
<a href="duty-roster.php"><button><i class="fa fa-calendar-check-o" style="color:#0f766e"></i> Duty Roster</button></a>
<a href="lesson-timetable.php"><button><i class="fa fa-calendar" style="color:#0f766e"></i> Lesson Timetable Entry</button></a>
<a href="lesson-timetable-report.php"><button><i class="fa fa-book" style="color:#0f766e"></i> Lesson Timetable Report</button></a>
<a href="course-registration-admin.php"><button><i class="fa fa-list-alt" style="color:#1d4ed8"></i> Course Registration</button></a>
<a href="report-approval-board.php"><button><i class="fa fa-check-circle" style="color:#0f766e"></i> Report Approval</button></a>
<a href="online-admission-admin.php"><button><i class="fa fa-globe" style="color:#0ea5e9"></i> Online Admission</button></a>
<a href="online-voting-admin.php"><button><i class="fa fa-trophy" style="color:#d97706"></i> Online Voting</button></a>
<a href="student-chat-monitor.php"><button><i class="fa fa-eye" style="color:#0f766e"></i> Student Chat Monitor</button></a>
<a href="student-chat-settings.php"><button><i class="fa fa-sliders" style="color:#0f766e"></i> Student Chat Control</button></a>
<a href="payments.php"><button><i class="fa fa-credit-card" style="color:#16a34a"></i> Payments</button></a>
<a href="teacher-billing-assignment.php"><button><i class="fa fa-users" style="color:#16a34a"></i> Teacher Billing Assignment</button></a>
<a href="house-entry.php"><button><i class="fa fa-home" style="color:teal"></i> House Entry</button></a>
<a href="house-master-assignment.php"><button><i class="fa fa-plus" style="color:teal"></i> House Master Assignment</button></a>
<a href="student-house-assignment.php"><button><i class="fa fa-users" style="color:teal"></i> Student House Assignment</button></a>
<a href="senior-house-assignment.php"><button><i class="fa fa-star" style="color:teal"></i> Senior House Assignment</button></a>
<a href="senior-house-dashboard.php"><button><i class="fa fa-dashboard" style="color:teal"></i> Senior House Dashboard</button></a>

<a href="upload-register.php"><button><i class="fa fa-upload" style="color:blue"></i> Upload Registers</button></a>
<a href="waec-analysis.php"><button><i class="fa fa-line-chart" style="color:teal"></i> WAEC Analysis</button></a>

<a href="viewusers.php"><button><i class="fa fa-user" style="color:brown"></i> View Teachers</button></a>
<a href="viewstudents.php"><button><i class="fa fa-user" style="color:maroon"></i> View Students</button></a>
<a href="backup_db.php"><button><i class="fa fa-book" style="color:green"></i> Backup </button></a>
<a href="generateapikey.php"><button><i class="fa fa-book" style="color:green"></i> API KEY </button></a>

<a href="logout.php"><button><i class="fa fa-power-off" style="color:red"></i> Logout </button></a>
</div>
<?php
}
?>
</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var dropdown = document.querySelector('[data-menu-dropdown]');
    if (!dropdown) {
        return;
    }

    var toggleButton = dropdown.querySelector('.dropbtnNew');

    function closeDropdown() {
        dropdown.classList.remove('is-open');
        toggleButton.setAttribute('aria-expanded', 'false');
    }

    toggleButton.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        var isOpen = dropdown.classList.toggle('is-open');
        toggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (event) {
        if (!dropdown.contains(event.target)) {
            closeDropdown();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDropdown();
        }
    });

    function startActivityHeartbeat() {
        var liveUsersNode = document.getElementById('top-live-users-count');
        var endpoint = 'user-activity-heartbeat.php';
        var isRunning = false;

        function heartbeat() {
            if (isRunning) {
                return;
            }
            isRunning = true;
            fetch(endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('heartbeat failed');
                }
                return response.json();
            }).then(function (payload) {
                if (liveUsersNode && payload && typeof payload.live_users !== 'undefined') {
                    liveUsersNode.textContent = payload.live_users;
                }
            }).catch(function () {
                // Keep the menu quiet if the heartbeat cannot complete.
            }).finally(function () {
                isRunning = false;
            });
        }

        heartbeat();
        window.setInterval(heartbeat, 60000);
    }

    startActivityHeartbeat();
});
</script>
</body>
</html>
