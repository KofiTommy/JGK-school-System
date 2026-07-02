<?php
$redirectPage = "upload-exam-score-entry.php";
$scoreType = "Exam Score";
$scoreLabel = "Exam Score";
$scoreLimit = 70;
$auditAction = "SCORE_UPLOAD_EXAM";

include(__DIR__.DIRECTORY_SEPARATOR."score-upload-import.php");
