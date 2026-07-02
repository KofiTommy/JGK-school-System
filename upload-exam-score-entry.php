<?php
$pageFile = "upload-exam-score-entry.php";
$pageTitle = "Upload Exam Scores";
$pageDescription = "Download the exam score template, complete it offline, and upload it for the selected class, subject, and session.";
$scoreType = "Exam Score";
$scoreLabel = "Exam Score";
$scoreLimit = 70;
$templatePage = "download-examscore-template.php";
$manualEntryPage = "exam-score-entry.php";
$importPage = "import-exam-scores-data.php";
$bodyModifierClass = "score-entry-page--exam";
$heroTitle = "Upload Exam Scores";
$uploadGuidanceTitle = "Before You Upload";
$heroTips = array(
    "Select the correct class and subject before uploading.",
    "Keep the template headings unchanged and enter scores in the last column only.",
    "Save the completed workbook as `.xlsx` when possible.",
    "Students with saved exam scores will be skipped automatically.",
    "Open Scores Report after upload if you want to review the saved marks."
);

include(__DIR__.DIRECTORY_SEPARATOR."score-upload-workspace.php");
