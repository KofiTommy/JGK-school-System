<?php
$pageFile = "upload-class-score-entry.php";
$pageTitle = "Upload Class Scores";
$pageDescription = "Download the class score template, complete it offline, and upload it for the selected class, subject, and session.";
$scoreType = "Class Score";
$scoreLabel = "Class Score";
$scoreLimit = 30;
$templatePage = "download-classscore-template.php";
$manualEntryPage = "class-score-entry.php";
$importPage = "import-class-scores-data.php";
$bodyModifierClass = "score-entry-page--class";
$heroTitle = "Upload Class Scores";
$uploadGuidanceTitle = "Before You Upload";
$heroTips = array(
    "Select the correct class and subject before uploading.",
    "Use the downloaded template so student IDs and subject details stay unchanged.",
    "Enter scores in the last column only.",
    "Save the completed workbook as `.xlsx` when possible.",
    "Use Manual Entry when you only need to update a few students."
);

include(__DIR__.DIRECTORY_SEPARATOR."score-upload-workspace.php");
