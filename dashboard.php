<?php

//session_start();
include("company.php");
echo "<h2 style='color:dodgerblue;margin-top:-30px;'>$_CompanyName</h2>";

if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Teacher")
{
  ?>

  <ul>
 <li>
      <a class="active" href="teacher-page.php"><i class="fa fa-home" ></i> Home</a>
      </li>

  
  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-globe" ></i> Tools</a>
    <div class="dropdown-content">
    <a href="view-subject-assigned.php"><i class="fa fa-book" ></i> View Subject(s) Assigned</a>
    
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-file" ></i> Examination</a>
    <div class="dropdown-content">
      <a href="class-score-entry.php"><i class="fa fa-plus" ></i> Class Score Entry</a>
     
   <!--  <a href="class-score.php">Class Score</a>-->
     <a href="exam-score-entry.php"><i class="fa fa-plus" ></i> Exam Score Entry</a>
     <hr>
     <a href="upload-class-score-entry.php"><i class="fa fa-upload" ></i> Upload Class Score Entry</a>
     <a href="upload-exam-score-entry.php"><i class="fa fa-upload" ></i> Upload Exam Score Entry</a>
     <a href="scores-report.php"><i class="fa fa-book" ></i> Scores Report</a>
     <a href="terminal-report.php"> <i class="fa fa-plus" ></i> Terminal Report</a>
     <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
     
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
    <a href="#" class="dropbtn"><i class="fa fa-file" ></i> Examination</a>
    <div class="dropdown-content">
      <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
     <a href="individual-terminal-report.php"><i class="fa fa-book" ></i> Terminal Report</a>
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

  <li  class="dropdown">
  <a  href="#"><i class="fa fa-book" ></i> Items</a>
 
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
          <a href="#" class="dropbtn"><i class="fa fa-file" ></i> Record</a>
          <div class="dropdown-content">
           <a href="class-registry.php"><i class="fa fa-plus" ></i> Class Registry</a>
           <a href="upload-class-registry.php"><i class="fa fa-upload" ></i>Upload Class Registry</a>
           <hr>
           <!--<a href="view-class-registry.php">View Class Registry</a>-->

           <a href="term-registry.php"><i class="fa fa-plus" ></i> Semester Registry</a>
           <a href="group-term-registry.php"><i class="fa fa-plus" ></i> Group Semester Registry</a>
           <a href="upload-semester-registry.php"><i class="fa fa-upload" ></i>Upload Semester Registry</a>          
          </div>
  </li>



  <li class="dropdown">
    <a href="#" class="dropbtn">Tools</a>
    <div class="dropdown-content">
    <a href="subject-classification.php"><i class="fa fa-plus" ></i> Subject Classification</a>
      <a href="view-subject-classified.php"><i class="fa fa-plus" ></i> View Subject Classified</a>
     <a href="subject-assignment.php"><i class="fa fa-plus" ></i> Subject Assignment</a>
    <a href="view-all-subject-assigned.php"><i class="fa fa-plus" ></i> View Subject(s) Assigned</a>
    
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-file" ></i> Examination</a>
    <div class="dropdown-content">
     <a href="all-class-score.php"><i class="fa fa-plus" ></i> Class Score</a>
     <a href="exam-score.php"><i class="fa fa-plus" ></i> Exam Score</a>
     <a href="student-terminal-data.php"><i class="fa fa-plus" ></i> Student Terminal Data</a>
     <a href="scores-report.php"><i class="fa fa-book" ></i> Scores Report</a>
    
     <a href="terminal-report.php"><i class="fa fa-book" ></i> Terminal Report</a>
     <a href="examinationtimetable.php"><i class="fa fa-plus" ></i> Exam Time Table Entry</a>
     <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
     
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

  <li  class="dropdown">
  <a  href="#"><i class="fa fa-book" ></i> Items</a>
 
  <div class="dropdown-content">
 
 <!--<a href="company-entry.php"><i class="fa fa-plus" ></i> Company Entry</a>
 -->
 <a href="company-entry.php"><i class="fa fa-plus" ></i> School Entry</a>
 <a href="branch-entry.php"><i class="fa fa-plus" ></i> Branch Entry</a>
 
 
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
    <a href="#" class="dropbtn"><i class="fa fa-plus" ></i> Tools</a>
    <div class="dropdown-content">
      
      <a href="smsreport.php"><i class="fa fa-send" ></i> Bulk SMS Exams Report</a>
    
    <a href="subject-classification.php"><i class="fa fa-plus" ></i> Subject Classification</a>
      <a href="view-subject-classified.php"><i class="fa fa-book" ></i> View Subject Classified</a>
     <a href="subject-assignment.php"><i class="fa fa-plus" ></i> Subject Assignment</a>
    <a href="view-all-subject-assigned.php"><i class="fa fa-book" ></i> View Subject(s) Assigned</a>
    
    </div>
  </li>

  <li class="dropdown">
    <a href="#" class="dropbtn"><i class="fa fa-file" ></i> Examination</a>
    <div class="dropdown-content">
   <!--  <a href="all-class-score.php"><i class="fa fa-plus" ></i> Class Score</a>
     <a href="exam-score.php"><i class="fa fa-plus" ></i> Exam Score</a>
   -->
     <a href="student-terminal-data.php"><i class="fa fa-plus" ></i> Student Terminal Data</a>
     <a href="terminal-report.php"><i class="fa fa-book" ></i> Terminal Report</a>
     <a href="scores-report.php"><i class="fa fa-book" ></i> Scores Report</a>
    
     <a href="examinationtimetable.php"><i class="fa fa-plus" ></i> Exam Time Table Entry</a>
     <a href="examinationtimetablereport.php"><i class="fa fa-book" ></i> Exam Time Table Report</a>
     
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
           
     <a href="student-terminal-data.php"><i class="fa fa-plus" ></i> Student Terminal Data</a>
     <a href="terminal-report.php"><i class="fa fa-book" ></i> Terminal Report</a>
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
