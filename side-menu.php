<?php

//session_start();
include("company.php");
if(!isset($con)){
  include_once("dbstring.php");
}
include_once("house-master-utils.php");
include_once("class-teacher-utils.php");
include_once("user-management-utils.php");
include_once("counselling-utils.php");
include_once("student-chat-utils.php");
ensure_house_tables($con);
ensure_class_teacher_table($con);
ensure_counselling_tables($con);
$_StudentChatEnabledForMenu = student_chat_is_enabled($con);
$_ShowHouseMasterLinks = false;
$_ShowSeniorHouseLinks = false;
$_HouseMasterDashboardLabel = "House Master Dashboard";
$_TeacherExtraAccessLinks = array();
$_ShowTeacherAttendanceLinks = false;
$_ShowTeacherCounsellingLink = false;
if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
   $_SESSION['ACCESSLEVEL'] === "user" &&
   $_SESSION['SYSTEMTYPE'] === "Teacher"){
  $_ShowHouseMasterLinks = house_master_has_assignment($con, $_SESSION['USERID']);
  $_ShowSeniorHouseLinks = house_master_has_senior_assignment($con, $_SESSION['USERID']);
  $_HouseMasterDashboardLabel = house_master_dashboard_label($con, $_SESSION['USERID']);
  $_TeacherExtraAccessLinks = um_teacher_extra_nav_links($con, $_SESSION['USERID']);
  $_ShowTeacherAttendanceLinks = class_teacher_has_any_assignment($con, $_SESSION['USERID']);
  $_ShowTeacherCounsellingLink = counselling_teacher_has_assignment($con, $_SESSION['USERID']);
}
$_ShowExeatManagerLinks = $_ShowHouseMasterLinks || $_ShowSeniorHouseLinks;
echo "<h2 style='color:dodgerblue;margin-top:-30px;'>$_CompanyName</h2>";

if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Teacher")
{
  ?>

  <ul>
    <li>
      <a class="active" href="teacher-page.php"><i class="fa fa-home" ></i> Home</a>
    </li>

    <li class="dropdown">
      <a href="#" class="dropbtn"><i class="fa fa-users" ></i> My Classes</a>
      <div class="dropdown-content">
        <a href="view-subject-assigned.php"><i class="fa fa-book" ></i> View Subject(s) Assigned</a>
        <a href="teacher-course-registration.php"><i class="fa fa-list-alt" ></i> Course Registration</a>
        <?php if($_ShowTeacherAttendanceLinks){ ?>
        <a href="student-attendance.php"><i class="fa fa-check-square-o" ></i> Student Attendance</a>
        <a href="student-attendance-report.php"><i class="fa fa-bar-chart" ></i> Attendance Summary</a>
        <?php } ?>
        <?php
        if($_ShowHouseMasterLinks){
          echo "<a href='house-master-dashboard.php'><i class='fa fa-home' ></i> ".htmlspecialchars($_HouseMasterDashboardLabel, ENT_QUOTES, 'UTF-8')."</a>";
        }
        if($_ShowExeatManagerLinks){
          echo "<a href='house-master-exeat.php'><i class='fa fa-check' ></i> House Exeat Management</a>";
        }
        if($_ShowSeniorHouseLinks){
          echo "<a href='senior-house-dashboard.php'><i class='fa fa-dashboard' ></i> Senior House Dashboard</a>";
        }
        if(!empty($_TeacherExtraAccessLinks)){
          foreach($_TeacherExtraAccessLinks as $_ExtraLink){
            echo "<a href='".htmlspecialchars($_ExtraLink['href'], ENT_QUOTES, 'UTF-8')."'><i class='fa ".htmlspecialchars($_ExtraLink['icon'], ENT_QUOTES, 'UTF-8')."' ></i> ".htmlspecialchars($_ExtraLink['label'], ENT_QUOTES, 'UTF-8')."</a>";
          }
        }
        ?>
      </div>
    </li>

    <li class="dropdown">
      <a href="#" class="dropbtn"><i class="fa fa-pencil" ></i> Scores</a>
      <div class="dropdown-content">
        <a href="class-score-entry.php"><i class="fa fa-plus" ></i> Class Score Entry</a>
        <a href="exam-score-entry.php"><i class="fa fa-plus" ></i> Exam Score Entry</a>
        <a href="upload-class-score-entry.php"><i class="fa fa-upload" ></i> Upload Class Score Entry</a>
        <a href="upload-exam-score-entry.php"><i class="fa fa-upload" ></i> Upload Exam Score Entry</a>
        <a href="upload-classexam-score.php"><i class="fa fa-upload" ></i> Upload Class & Exam Scores</a>
      </div>
    </li>

    <li class="dropdown">
      <a href="#" class="dropbtn"><i class="fa fa-file-text-o" ></i> Results</a>
      <div class="dropdown-content">
        <?php if($_ShowTeacherAttendanceLinks){ ?>
        <a href="student-terminal-data.php"><i class="fa fa-plus" ></i> Student Remark Data</a>
        <a href="upload-student-remark-data.php"><i class="fa fa-upload" ></i> Upload Students Remark Data</a>
        <?php } ?>
        <a href="scores-report.php"><i class="fa fa-book" ></i> Scores Report</a>
        <a href="terminal-report.php"><i class="fa fa-plus" ></i> Terminal Report</a>
      </div>
    </li>

    <li class="dropdown">
      <a href="#" class="dropbtn"><i class="fa fa-calendar" ></i> Resources</a>
      <div class="dropdown-content">
        <a href="lesson-timetable-report.php"><i class="fa fa-calendar" ></i> Lesson Timetable</a>
        <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
        <?php if($_ShowTeacherCounsellingLink){ ?>
        <a href="guidance-counselling.php"><i class="fa fa-heartbeat" ></i> Counselling Cases</a>
        <?php } ?>
        <a href="online-class.php"><i class="fa fa-video-camera" ></i> Online Class</a>
        <a href="online-voting.php"><i class="fa fa-trophy" ></i> Online Voting</a>
      </div>
    </li>

</ul>

<?php
}
else if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Student")
{
  ?>

  <ul>
    <li>
      <a class="active" href="student-page.php"><i class="fa fa-home" ></i> Home</a>
    </li>

    <li class="dropdown">
      <a href="#" class="dropbtn"><i class="fa fa-graduation-cap" ></i> Academics</a>
      <div class="dropdown-content">
        <a href="registeredclass.php"><i class="fa fa-folder-o" ></i> Registered Class</a>
        <a href="registeredsubject.php"><i class="fa fa-folder-o" ></i> Registered Subject</a>
        <a href="student-course-registration.php"><i class="fa fa-list-alt" ></i> Course Registration</a>
        <a href="lesson-timetable-report.php"><i class="fa fa-clock-o" ></i> Lesson Timetable</a>
        <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
        <a href="individual-terminal-report.php"><i class="fa fa-book" ></i> Terminal Report</a>
        <a href="online-class.php"><i class="fa fa-video-camera" ></i> Join Online Class</a>
        <a href="student-attendance-report.php"><i class="fa fa-bar-chart" ></i> My Attendance</a>
      </div>
    </li>

    <li class="dropdown">
      <a href="#" class="dropbtn"><i class="fa fa-money" ></i> Finance</a>
      <div class="dropdown-content">
        <a href="bills.php"><i class="fa fa-money" ></i> Bills</a>
        <a href="account-statements.php"><i class="fa fa-money" ></i> Account Statement</a>
      </div>
    </li>

    <li class="dropdown">
      <a href="#" class="dropbtn"><i class="fa fa-comments" ></i> Communication</a>
      <div class="dropdown-content">
        <a href="messages.php"><i class="fa fa-comments" ></i> Message Box</a>
        <?php if($_StudentChatEnabledForMenu){ ?>
        <a href="student-chat.php"><i class="fa fa-user-plus" ></i> Student Chat</a>
        <?php } ?>
        <a href="online-voting.php"><i class="fa fa-trophy" ></i> Online Voting</a>
      </div>
    </li>

    <li class="dropdown">
      <a href="#" class="dropbtn"><i class="fa fa-life-ring" ></i> Welfare</a>
      <div class="dropdown-content">
        <a href="student-exeat-request.php"><i class="fa fa-file" ></i> Request Exeat</a>
        <a href="guidance-counselling.php"><i class="fa fa-heartbeat" ></i> Guidance &amp; Counselling</a>
      </div>
    </li>


  </ul>

<?php
}
else if($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="super_user"){
 ?>
<ul>
  <li>
      <a class="active" href="super.php"><i class="fa fa-home" ></i> Home</a>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-cogs" ></i> Setup</a>
    <div class="dropdown-content">
      <a href="company-entry.php"><i class="fa fa-plus" ></i> School Entry</a>
      <a href="branch-entry.php"><i class="fa fa-plus" ></i> Branch Entry</a>
      <a href="batch-entry.php"><i class="fa fa-plus" ></i> Batch Entry</a>
      <a href="subject-entry.php"><i class="fa fa-plus" ></i> Subject Entry</a>
      <a href="class-entry.php"><i class="fa fa-plus" ></i> Class Entry</a>
      <a href="school-data-entry.php"><i class="fa fa-plus" ></i> School Data Entry</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-folder-o" ></i> Student Records</a>
    <div class="dropdown-content">
      <a href="class-registry.php"><i class="fa fa-plus" ></i> Class Registry</a>
      <a href="upload-class-registry.php"><i class="fa fa-upload" ></i> Upload Class Registry</a>
      <a href="view-class-registry.php"><i class="fa fa-search" ></i> View Class Registry</a>
      <a href="term-registry.php"><i class="fa fa-plus" ></i> Semester Registry</a>
      <a href="group-term-registry.php"><i class="fa fa-plus" ></i> Group Semester Registry</a>
      <a href="upload-semester-registry.php"><i class="fa fa-upload" ></i> Upload Semester Registry</a>
      <a href="promotion-center.php"><i class="fa fa-level-up" ></i> Promotion Center</a>
      <a href="continuing-students.php"><i class="fa fa-users" ></i> Continuing Students</a>
      <a href="student-history.php"><i class="fa fa-history" ></i> Student Transcript</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-book" ></i> Academics</a>
    <div class="dropdown-content">
      <a href="subject-classification.php"><i class="fa fa-plus" ></i> Subject Classification</a>
      <a href="view-subject-classified.php"><i class="fa fa-book" ></i> View Subject Classified</a>
      <a href="subject-assignment.php"><i class="fa fa-plus" ></i> Subject Assignment</a>
      <a href="view-all-subject-assigned.php"><i class="fa fa-book" ></i> View Subject(s) Assigned</a>
      <a href="course-registration-admin.php"><i class="fa fa-list-alt" ></i> Course Registration</a>
      <a href="class-teacher-assignment.php"><i class="fa fa-plus" ></i> Class Teacher Assignment</a>
      <a href="student-attendance.php"><i class="fa fa-check-square-o" ></i> Student Attendance</a>
      <a href="student-attendance-report.php"><i class="fa fa-bar-chart" ></i> Attendance Summary</a>
      <a href="duty-roster.php"><i class="fa fa-calendar-check-o" ></i> Duty Roster</a>
      <a href="lesson-timetable.php"><i class="fa fa-calendar" ></i> Lesson Timetable Entry</a>
      <a href="lesson-timetable-report.php"><i class="fa fa-book" ></i> Lesson Timetable Report</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-file-text-o" ></i> Results</a>
    <div class="dropdown-content">
      <a href="class-score-entry.php"><i class="fa fa-pencil" ></i> Class Score Entry</a>
      <a href="exam-score-entry.php"><i class="fa fa-pencil" ></i> Exam Score Entry</a>
      <a href="upload-class-score-entry.php"><i class="fa fa-upload" ></i> Upload Class Score</a>
      <a href="upload-exam-score-entry.php"><i class="fa fa-upload" ></i> Upload Exam Score</a>
      <a href="upload-classexam-score.php"><i class="fa fa-upload" ></i> Upload Class & Exam Scores</a>
      <a href="student-terminal-data.php"><i class="fa fa-plus" ></i> Student Remark Data</a>
      <a href="upload-student-remark-data.php"><i class="fa fa-upload" ></i> Upload Students Remark Data</a>
      <a href="terminal-report.php"><i class="fa fa-book" ></i> Terminal Report</a>
      <a href="report-approval-board.php"><i class="fa fa-check-circle" ></i> Report Approval</a>
      <a href="scores-report.php"><i class="fa fa-book" ></i> Scores Report</a>
      <a href="waec-analysis.php"><i class="fa fa-line-chart" ></i> WAEC Analysis</a>
      <a href="internal-exam-analysis.php"><i class="fa fa-bar-chart" ></i> Internal Exams Analysis</a>
      <a href="examanalysis-subject.php"><i class="fa fa-book" ></i> Exam Analysis: Subject</a>
      <a href="examanalysis-rank.php"><i class="fa fa-trophy" ></i> Exam Analysis: Rank</a>
      <a href="examinationtimetable.php"><i class="fa fa-plus" ></i> Exam Time Table Entry</a>
      <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-money" ></i> Finance</a>
    <div class="dropdown-content">
      <a href="payments.php"><i class="fa fa-credit-card" ></i> Payments</a>
      <a href="class-billing.php"><i class="fa fa-cogs" ></i> Billing Manager</a>
      <a href="teacher-billing-assignment.php"><i class="fa fa-users" ></i> Teacher Billing Assignment</a>
      <a href="student-billing.php"><i class="fa fa-plus" ></i> Bill Student</a>
      <a href="group-student-billing.php"><i class="fa fa-plus" ></i> Bill Group Students</a>
      <a href="print-student-bills.php"><i class="fa fa-print" ></i> Print Student Bills</a>
      <a href="daily-report.php"><i class="fa fa-book" ></i> Daily Report</a>
      <a href="payment-analysis.php"><i class="fa fa-book" ></i> Payment Report</a>
      <a href="bills-report.php"><i class="fa fa-book" ></i> Bills Report</a>
      <a href="item-bill-report.php"><i class="fa fa-book" ></i> Item Bill Report</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-home" ></i> Operations</a>
    <div class="dropdown-content">
      <a href="user-management.php"><i class="fa fa-users" ></i> User Management</a>
      <a href="user-visit-history.php"><i class="fa fa-clock-o" ></i> User Visit History</a>
      <a href="house-entry.php"><i class="fa fa-home" ></i> House Entry</a>
      <a href="house-master-assignment.php"><i class="fa fa-plus" ></i> House Master Assignment</a>
      <a href="student-house-assignment.php"><i class="fa fa-users" ></i> Student House Assignment</a>
      <a href="senior-house-assignment.php"><i class="fa fa-star" ></i> Senior House Assignment</a>
      <a href="senior-house-dashboard.php"><i class="fa fa-dashboard" ></i> Senior House Dashboard</a>
      <a href="counsellor-assignment.php"><i class="fa fa-heartbeat" ></i> Counsellor Assignment</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-bullhorn" ></i> Communication</a>
    <div class="dropdown-content">
      <a href="online-admission-admin.php"><i class="fa fa-globe" ></i> Online Admission</a>
      <a href="online-voting-admin.php"><i class="fa fa-trophy" ></i> Online Voting</a>
      <a href="notification.php"><i class="fa fa-plus" ></i> Send Notification</a>
      <a href="student-chat-monitor.php"><i class="fa fa-eye" ></i> Student Chat Monitor</a>
      <a href="student-chat-settings.php"><i class="fa fa-sliders" ></i> Student Chat Control</a>
      <a href="enablesmsalert.php"><i class="fa fa-phone" ></i> Enable SMS Alert</a>
      <a href="smsreport.php"><i class="fa fa-phone" ></i> SMS Reporting</a>
      <a href="smsreportdata.php"><i class="fa fa-database" ></i> SMS Data</a>
    </div>
  </li>
  
  </ul>

<?php
}
else if($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="normal_user"){
 ?>
<ul>
  <li>
      <a class="active" href="admin.php"><i class="fa fa-home" ></i> Home</a>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-cogs" ></i> Setup</a>
    <div class="dropdown-content">
      <a href="company-entry.php"><i class="fa fa-plus" ></i> School Entry</a>
      <a href="branch-entry.php"><i class="fa fa-plus" ></i> Branch Entry</a>
      <a href="batch-entry.php"><i class="fa fa-plus" ></i> Batch Entry</a>
      <a href="subject-entry.php"><i class="fa fa-plus" ></i> Subject Entry</a>
      <a href="class-entry.php"><i class="fa fa-plus" ></i> Class Entry</a>
      <a href="school-data-entry.php"><i class="fa fa-plus" ></i> School Data Entry</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-folder-o" ></i> Student Records</a>
    <div class="dropdown-content">
      <a href="class-registry.php"><i class="fa fa-plus" ></i> Class Registry</a>
      <a href="upload-class-registry.php"><i class="fa fa-upload" ></i> Upload Class Registry</a>
      <a href="view-class-registry.php"><i class="fa fa-search" ></i> View Class Registry</a>
      <a href="term-registry.php"><i class="fa fa-plus" ></i> Semester Registry</a>
      <a href="group-term-registry.php"><i class="fa fa-plus" ></i> Group Semester Registry</a>
      <a href="upload-semester-registry.php"><i class="fa fa-upload" ></i> Upload Semester Registry</a>
      <a href="promotion-center.php"><i class="fa fa-level-up" ></i> Promotion Center</a>
      <a href="continuing-students.php"><i class="fa fa-users" ></i> Continuing Students</a>
      <a href="student-history.php"><i class="fa fa-history" ></i> Student Transcript</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-book" ></i> Academics</a>
    <div class="dropdown-content">
      <a href="subject-classification.php"><i class="fa fa-plus" ></i> Subject Classification</a>
      <a href="view-subject-classified.php"><i class="fa fa-book" ></i> View Subject Classified</a>
      <a href="subject-assignment.php"><i class="fa fa-plus" ></i> Subject Assignment</a>
      <a href="view-all-subject-assigned.php"><i class="fa fa-book" ></i> View Subject(s) Assigned</a>
      <a href="course-registration-admin.php"><i class="fa fa-list-alt" ></i> Course Registration</a>
      <a href="class-teacher-assignment.php"><i class="fa fa-plus" ></i> Class Teacher Assignment</a>
      <a href="student-attendance.php"><i class="fa fa-check-square-o" ></i> Student Attendance</a>
      <a href="student-attendance-report.php"><i class="fa fa-bar-chart" ></i> Attendance Summary</a>
      <a href="duty-roster.php"><i class="fa fa-calendar-check-o" ></i> Duty Roster</a>
      <a href="lesson-timetable.php"><i class="fa fa-calendar" ></i> Lesson Timetable Entry</a>
      <a href="lesson-timetable-report.php"><i class="fa fa-book" ></i> Lesson Timetable Report</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-file-text-o" ></i> Results</a>
    <div class="dropdown-content">
      <a href="student-terminal-data.php"><i class="fa fa-plus" ></i> Student Remark Data</a>
      <a href="upload-student-remark-data.php"><i class="fa fa-upload" ></i> Upload Students Remark Data</a>
      <a href="continuous-assessment.php"><i class="fa fa-folder-o" ></i> Continuous Assessment</a>
      <a href="terminal-report.php"><i class="fa fa-book" ></i> Terminal Report</a>
      <a href="report-approval-board.php"><i class="fa fa-check-circle" ></i> Report Approval</a>
      <a href="scores-report-all.php"><i class="fa fa-book" ></i> Scores Report</a>
      <a href="waec-analysis.php"><i class="fa fa-line-chart" ></i> WAEC Analysis</a>
      <a href="internal-exam-analysis.php"><i class="fa fa-bar-chart" ></i> Internal Exams Analysis</a>
      <a href="examanalysis-subject.php"><i class="fa fa-book" ></i> Exam Analysis: Subject</a>
      <a href="examanalysis-rank.php"><i class="fa fa-trophy" ></i> Exam Analysis: Rank</a>
      <a href="examinationtimetable.php"><i class="fa fa-plus" ></i> Exam Time Table Entry</a>
      <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-money" ></i> Finance</a>
    <div class="dropdown-content">
      <a href="payments.php"><i class="fa fa-credit-card" ></i> Payments</a>
      <a href="class-billing.php"><i class="fa fa-cogs" ></i> Billing Manager</a>
      <a href="teacher-billing-assignment.php"><i class="fa fa-users" ></i> Teacher Billing Assignment</a>
      <a href="student-billing.php"><i class="fa fa-plus" ></i> Bill Student</a>
      <a href="group-student-billing.php"><i class="fa fa-plus" ></i> Bill Group Students</a>
      <a href="print-student-bills.php"><i class="fa fa-print" ></i> Print Student Bills</a>
      <a href="daily-report.php"><i class="fa fa-book" ></i> Daily Report</a>
      <a href="payment-analysis.php"><i class="fa fa-book" ></i> Payment Report</a>
      <a href="bills-report.php"><i class="fa fa-book" ></i> Bills Report</a>
      <a href="item-bill-report.php"><i class="fa fa-book" ></i> Item Bill Report</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-home" ></i> Operations</a>
    <div class="dropdown-content">
      <a href="user-management.php"><i class="fa fa-users" ></i> User Management</a>
      <a href="user-visit-history.php"><i class="fa fa-clock-o" ></i> User Visit History</a>
      <a href="house-entry.php"><i class="fa fa-home" ></i> House Entry</a>
      <a href="house-master-assignment.php"><i class="fa fa-plus" ></i> House Master Assignment</a>
      <a href="student-house-assignment.php"><i class="fa fa-users" ></i> Student House Assignment</a>
      <a href="senior-house-assignment.php"><i class="fa fa-star" ></i> Senior House Assignment</a>
      <a href="senior-house-dashboard.php"><i class="fa fa-dashboard" ></i> Senior House Dashboard</a>
      <a href="counsellor-assignment.php"><i class="fa fa-heartbeat" ></i> Counsellor Assignment</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-bullhorn" ></i> Communication</a>
    <div class="dropdown-content">
      <a href="online-admission-admin.php"><i class="fa fa-globe" ></i> Online Admission</a>
      <a href="online-voting-admin.php"><i class="fa fa-trophy" ></i> Online Voting</a>
      <a href="notification.php"><i class="fa fa-plus" ></i> Send Notification</a>
      <a href="student-chat-monitor.php"><i class="fa fa-eye" ></i> Student Chat Monitor</a>
      <a href="student-chat-settings.php"><i class="fa fa-sliders" ></i> Student Chat Control</a>
      <a href="enablesmsalert.php"><i class="fa fa-phone" ></i> Enable SMS Alert</a>
      <a href="smsreport.php"><i class="fa fa-phone" ></i> SMS Reporting</a>
      <a href="smsreportdata.php"><i class="fa fa-database" ></i> SMS Data</a>
    </div>
  </li>
  
  </ul>

<?php

}
else if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Headmaster"){
?>
<ul>
  <li>
      <a class="active" href="headmaster-page.php"><i class="fa fa-home" ></i> Home</a>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-dashboard" ></i> Overview</a>
    <div class="dropdown-content">
      <a href="student-history.php"><i class="fa fa-history" ></i> Student Transcript</a>
      <a href="viewstudents.php"><i class="fa fa-graduation-cap" ></i> View Students</a>
      <a href="continuing-students.php"><i class="fa fa-users" ></i> Continuing Students</a>
      <a href="viewusers.php"><i class="fa fa-users" ></i> Teachers List</a>
      <a href="duty-roster.php"><i class="fa fa-calendar-check-o" ></i> Teacher On Duty</a>
      <a href="senior-house-dashboard.php"><i class="fa fa-shield" ></i> Senior House Overview</a>
      <a href="view-class-registry.php"><i class="fa fa-folder-open" ></i> View Class Registry</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-graduation-cap" ></i> Academics</a>
    <div class="dropdown-content">
      <a href="student-attendance-report.php"><i class="fa fa-bar-chart" ></i> Attendance Summary</a>
      <a href="terminal-report.php"><i class="fa fa-file-text-o" ></i> Examination Report</a>
      <a href="internal-exam-analysis.php"><i class="fa fa-bar-chart" ></i> Internal Exams Analysis</a>
      <a href="waec-analysis.php"><i class="fa fa-line-chart" ></i> WAEC Analysis</a>
      <a href="lesson-timetable-report.php"><i class="fa fa-calendar" ></i> Lesson Timetable</a>
      <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-money" ></i> Finance</a>
    <div class="dropdown-content">
      <a href="daily-report.php"><i class="fa fa-book" ></i> Daily Report</a>
      <a href="payment-analysis.php"><i class="fa fa-line-chart" ></i> Payment Report</a>
      <a href="bills-report.php"><i class="fa fa-files-o" ></i> Bills Report</a>
      <a href="item-bill-report.php"><i class="fa fa-list" ></i> Item Bill Report</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-bullhorn" ></i> Communication</a>
    <div class="dropdown-content">
      <a href="online-admission-admin.php"><i class="fa fa-globe" ></i> Online Admission</a>
      <a href="messages.php"><i class="fa fa-comments" ></i> Message Box</a>
      <a href="notification.php"><i class="fa fa-bullhorn" ></i> Send Notification</a>
    </div>
  </li>
</ul>

<?php
}
else if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="AssistantHeadAcademic"){
 ?>
<ul>
  <li>
      <a class="active" href="assistant-head-academics-page.php"><i class="fa fa-home" ></i> Home</a>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-dashboard" ></i> Overview</a>
    <div class="dropdown-content">
      <a href="student-history.php"><i class="fa fa-history" ></i> Student Transcript</a>
      <a href="continuing-students.php"><i class="fa fa-users" ></i> Continuing Students</a>
      <a href="promotion-center.php"><i class="fa fa-level-up" ></i> Promotion Center</a>
      <a href="view-class-registry.php"><i class="fa fa-folder-open" ></i> View Class Registry</a>
      <a href="term-registry.php"><i class="fa fa-plus" ></i> Semester Registry</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-book" ></i> Academic Setup</a>
    <div class="dropdown-content">
      <a href="subject-classification.php"><i class="fa fa-book" ></i> Subject Classification</a>
      <a href="subject-assignment.php"><i class="fa fa-plus" ></i> Subject Assignment</a>
      <a href="view-all-subject-assigned.php"><i class="fa fa-book" ></i> Assigned Subjects</a>
      <a href="class-teacher-assignment.php"><i class="fa fa-users" ></i> Class Teacher Assignment</a>
      <a href="course-registration-admin.php"><i class="fa fa-list-alt" ></i> Course Registration</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-graduation-cap" ></i> Monitoring</a>
    <div class="dropdown-content">
      <a href="student-attendance-report.php"><i class="fa fa-bar-chart" ></i> Attendance Summary</a>
      <a href="terminal-report.php"><i class="fa fa-file-text-o" ></i> Examination Report</a>
      <a href="report-approval-board.php"><i class="fa fa-check-circle" ></i> Report Approval</a>
      <a href="internal-exam-analysis.php"><i class="fa fa-bar-chart" ></i> Internal Exams Analysis</a>
      <a href="waec-analysis.php"><i class="fa fa-line-chart" ></i> WAEC Analysis</a>
      <a href="lesson-timetable-report.php"><i class="fa fa-calendar" ></i> Lesson Timetable</a>
      <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-bullhorn" ></i> Communication</a>
    <div class="dropdown-content">
      <a href="messages.php"><i class="fa fa-comments" ></i> Message Box</a>
      <a href="notification.php"><i class="fa fa-bullhorn" ></i> Send Notification</a>
    </div>
  </li>
</ul>

<?php
}
else if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="User"){
 ?>
<ul>
<li>
<a class="active" href="user.php"><i class="fa fa-home" ></i> Home</a>
</li>
<li  class="dropdown">
<a  href="#"><i class="fa fa-book" ></i> Items</a>
  <div class="dropdown-content">
 <a href="batch-entry.php"><i class="fa fa-plus" ></i> Batch Entry</a>
 <a href="subject-entry.php"><i class="fa fa-plus" ></i> Subject Entry</a>
 <a href="class-entry.php"><i class="fa fa-plus" ></i> Class Entry</a>
 <a href="school-data-entry.php"><i class="fa fa-plus" ></i> School Data Entry</a>
  </div>
  </li>

  <li class="dropdown">
          <a href="#" class="dropbtn"><i class="fa fa-file" ></i> Record</a>
          <div class="dropdown-content">
           <a href="class-registry.php"><i class="fa fa-plus" ></i> Class Registry</a>
           <a href="upload-class-registry.php"><i class="fa fa-upload" ></i> Upload Class Registry</a>
           
           <!--<a href="view-class-registry.php">View Class Registry</a>-->
           <hr>
           <a href="term-registry.php"><i class="fa fa-plus" ></i> Semester Registry</a>
           <a href="group-term-registry.php"><i class="fa fa-plus" ></i> Group Semester Registry</a>
           <a href="upload-semester-registry.php"><i class="fa fa-upload" ></i> Upload Semester Registry</a>
          </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn">Tools</a>
    <div class="dropdown-content">
      <a href="smsreport.php"><i class="fa fa-plus" ></i> Bulk SMS Exams Report</a>
    
    <a href="subject-classification.php"><i class="fa fa-plus" ></i> Subject Classification</a>
      <a href="view-subject-classified.php"><i class="fa fa-plus" ></i> View Subject Classified</a>
     <a href="subject-assignment.php"><i class="fa fa-plus" ></i> Subject Assignment</a>
    <a href="course-registration-admin.php"><i class="fa fa-list-alt" ></i> Course Registration</a>
    <a href="view-all-subject-assigned.php"><i class="fa fa-plus" ></i> View Subject(s) Assigned</a>
    
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-file" ></i> Examination</a>
    <div class="dropdown-content">
     <!--<a href="all-class-score.php"><i class="fa fa-plus" ></i> Class Score</a>
     <a href="exam-score.php"><i class="fa fa-plus" ></i> Exam Score</a>
     -->

     <a href="examinationtimetable.php"><i class="fa fa-plus" ></i> Exam Time Table Entry</a>
     <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
     <a href="lesson-timetable.php"><i class="fa fa-calendar" ></i> Lesson Timetable Entry</a>
     <a href="lesson-timetable-report.php"><i class="fa fa-book" ></i> Lesson Timetable Report</a>
     <a href="online-voting-admin.php"><i class="fa fa-trophy" ></i> Online Voting</a>
            
     <a href="student-terminal-data.php"><i class="fa fa-plus" ></i> Student Terminal Data</a>
     <a href="terminal-report.php"><i class="fa fa-book" ></i> Terminal Report</a>
     <a href="report-approval-board.php"><i class="fa fa-check-circle" ></i> Report Approval</a>
    <a href="scores-report.php"><i class="fa fa-book" ></i> Scores Report</a>
    
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-globe" ></i> Notice</a>
    <div class="dropdown-content">
     <a href="notification.php"><i class="fa fa-plus" ></i> Send Notification</a>

    </div>
  </li>
  </ul>

<?php
}
?>

<script>
(function () {
  function heartbeat() {
    fetch('user-activity-heartbeat.php', {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).catch(function () {
      // Silent heartbeat to keep activity fresh.
    });
  }

  heartbeat();
  window.setInterval(heartbeat, 60000);
})();
</script>
