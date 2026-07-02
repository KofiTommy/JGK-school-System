<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("voting-utils.php");
ensure_voting_tables($con);

if(!voting_is_admin()){
    header("location:index.php");
    exit();
}

function ova_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function ova_alert($type, $message){
    $class = "vote-alert vote-alert--info";
    if($type === "success"){
        $class = "vote-alert vote-alert--success";
    }elseif($type === "warning"){
        $class = "vote-alert vote-alert--warning";
    }elseif($type === "error"){
        $class = "vote-alert vote-alert--error";
    }
    return "<div class=\"".$class."\">".ova_esc($message)."</div>";
}

function ova_flash_set($markup){
    $_SESSION["ONLINE_VOTING_ADMIN_MESSAGE"] = (string)$markup;
}

function ova_flash_take(){
    if(!isset($_SESSION["ONLINE_VOTING_ADMIN_MESSAGE"])){
        return "";
    }
    $message = (string)$_SESSION["ONLINE_VOTING_ADMIN_MESSAGE"];
    unset($_SESSION["ONLINE_VOTING_ADMIN_MESSAGE"]);
    return $message;
}

function ova_datetime_for_input($value){
    $value = trim((string)$value);
    if($value === ""){
        return "";
    }
    $time = strtotime($value);
    if(!$time){
        return "";
    }
    return date("Y-m-d\\TH:i", $time);
}

function ova_datetime_to_db($value){
    $value = trim((string)$value);
    if($value === ""){
        return "";
    }
    $value = str_replace("T", " ", $value);
    $time = strtotime($value);
    if(!$time){
        return "";
    }
    return date("Y-m-d H:i:s", $time);
}

function ova_admin_url($params = array(), $hash = ""){
    $clean = array();
    foreach((array)$params as $key => $value){
        if($value === null){
            continue;
        }
        $value = trim((string)$value);
        if($value !== ""){
            $clean[$key] = $value;
        }
    }
    $query = http_build_query($clean);
    return "online-voting-admin.php".($query !== "" ? "?".$query : "").$hash;
}

function ova_history_datetime($value){
    $value = trim((string)$value);
    if($value === ""){
        return "";
    }
    $time = strtotime($value);
    if(!$time){
        return $value;
    }
    return date("d M Y, h:i A", $time);
}

function ova_candidate_changed_fields($before, $after){
    $labels = array(
        "contestid" => "Contest",
        "candidatename" => "Candidate Name",
        "contestantnumber" => "Contestant Number",
        "classlabel" => "Class",
        "houselabel" => "House",
        "gender" => "Gender",
        "videolink" => "Video Link",
        "slogan" => "Slogan",
        "profiletext" => "Campaign Message",
        "displayorder" => "Display Order",
        "status" => "Status"
    );
    $changed = array();
    foreach($labels as $key => $label){
        $beforeValue = trim((string)(isset($before[$key]) ? $before[$key] : ""));
        $afterValue = trim((string)(isset($after[$key]) ? $after[$key] : ""));
        if($beforeValue !== $afterValue){
            $changed[] = $label;
        }
    }
    return $changed;
}

$branchId = voting_default_branch_id($con);
$branchIdEsc = mysqli_real_escape_string($con, $branchId);
$flashMessage = ova_flash_take();

$contestForm = array(
    "contestid" => "",
    "title" => "",
    "tagline" => "",
    "description" => "",
    "votereligibility" => "both",
    "livemode" => "full",
    "pricepervote" => "1.00",
    "minvotes" => "1",
    "maxvotes" => "100",
    "startsat" => "",
    "endsat" => "",
    "status" => "draft"
);

$candidateForm = array(
    "candidateid" => "",
    "contestid" => "",
    "candidatename" => "",
    "contestantnumber" => "",
    "classlabel" => "",
    "houselabel" => "",
    "gender" => "",
    "videolink" => "",
    "slogan" => "",
    "profiletext" => "",
    "displayorder" => "0",
    "status" => "active",
    "photofile" => "",
    "videofile" => ""
);

if(isset($_POST["save_voting_contest"])){
    foreach($contestForm as $key => $value){
        $contestForm[$key] = trim((string)(isset($_POST[$key]) ? $_POST[$key] : ""));
    }

    $errors = array();
    if($branchId === ""){
        $errors[] = "The branch setup is not ready for online voting.";
    }
    if($contestForm["title"] === ""){
        $errors[] = "Contest title is required.";
    }
    if(!in_array($contestForm["votereligibility"], array("students", "teachers", "both"), true)){
        $contestForm["votereligibility"] = "both";
    }
    if(!in_array($contestForm["livemode"], array("full", "top3", "totals", "hidden"), true)){
        $contestForm["livemode"] = "full";
    }
    if(!in_array($contestForm["status"], array("draft", "open", "closed", "archived"), true)){
        $contestForm["status"] = "draft";
    }
    $pricePerVote = (float)$contestForm["pricepervote"];
    $minVotes = max(1, (int)$contestForm["minvotes"]);
    $maxVotes = max($minVotes, (int)$contestForm["maxvotes"]);
    $startAt = ova_datetime_to_db($contestForm["startsat"]);
    $endAt = ova_datetime_to_db($contestForm["endsat"]);
    if($startAt !== "" && $endAt !== "" && strtotime($endAt) <= strtotime($startAt)){
        $errors[] = "Closing time must be later than the opening time.";
    }

    if(empty($errors)){
        $contestId = $contestForm["contestid"] !== "" ? $contestForm["contestid"] : voting_generate_id("VOTE_CONTEST_");
        $contestIdEsc = mysqli_real_escape_string($con, $contestId);
        $titleEsc = mysqli_real_escape_string($con, $contestForm["title"]);
        $taglineEsc = mysqli_real_escape_string($con, $contestForm["tagline"]);
        $descriptionEsc = mysqli_real_escape_string($con, $contestForm["description"]);
        $eligibilityEsc = mysqli_real_escape_string($con, $contestForm["votereligibility"]);
        $liveModeEsc = mysqli_real_escape_string($con, $contestForm["livemode"]);
        $statusEsc = mysqli_real_escape_string($con, $contestForm["status"]);
        $recordedByEsc = mysqli_real_escape_string($con, voting_current_user_id());
        $startSql = $startAt !== "" ? "'".mysqli_real_escape_string($con, $startAt)."'" : "NULL";
        $endSql = $endAt !== "" ? "'".mysqli_real_escape_string($con, $endAt)."'" : "NULL";

        $exists = voting_fetch_contest_by_id($con, $contestId, $branchId);
        if($exists){
            $updated = @mysqli_query($con, "UPDATE tblvotingcontest SET
                title='$titleEsc',
                tagline='$taglineEsc',
                description='$descriptionEsc',
                votereligibility='$eligibilityEsc',
                livemode='$liveModeEsc',
                pricepervote='".number_format($pricePerVote, 2, '.', '')."',
                minvotes='".(int)$minVotes."',
                maxvotes='".(int)$maxVotes."',
                startsat=$startSql,
                endsat=$endSql,
                status='$statusEsc',
                updatedat=NOW()
                WHERE contestid='$contestIdEsc' AND branchid='$branchIdEsc'
                LIMIT 1");
            if($updated){
                ova_flash_set(ova_alert("success", "Contest updated successfully."));
                header("location:".ova_admin_url(array("contest" => $contestId), "#contest-form"));
                exit();
            }
            $flashMessage = ova_alert("error", "The contest could not be updated right now.");
        }else{
            $saved = @mysqli_query($con, "INSERT INTO tblvotingcontest(
                contestid, branchid, title, tagline, description, contesttype, votereligibility, livemode,
                pricepervote, minvotes, maxvotes, startsat, endsat, status, recordedby, datetimeentry, updatedat
            ) VALUES (
                '$contestIdEsc', '$branchIdEsc', '$titleEsc', '$taglineEsc', '$descriptionEsc', 'pageantry', '$eligibilityEsc', '$liveModeEsc',
                '".number_format($pricePerVote, 2, '.', '')."', '".(int)$minVotes."', '".(int)$maxVotes."', $startSql, $endSql, '$statusEsc', '$recordedByEsc', NOW(), NOW()
            )");
            if($saved){
                ova_flash_set(ova_alert("success", "Contest created successfully."));
                header("location:".ova_admin_url(array("contest" => $contestId), "#contest-form"));
                exit();
            }
            $flashMessage = ova_alert("error", "The contest could not be created right now.");
        }
    }else{
        $flashMessage = ova_alert("warning", implode(" ", $errors));
    }
}

if(isset($_POST["set_voting_contest_status"])){
    $contestId = trim((string)(isset($_POST["contestid"]) ? $_POST["contestid"] : ""));
    $status = trim((string)(isset($_POST["status"]) ? $_POST["status"] : ""));
    if($contestId !== "" && in_array($status, array("draft", "open", "closed", "archived"), true)){
        $contestIdEsc = mysqli_real_escape_string($con, $contestId);
        $statusEsc = mysqli_real_escape_string($con, $status);
        $updated = @mysqli_query($con, "UPDATE tblvotingcontest SET status='$statusEsc', updatedat=NOW()
            WHERE contestid='$contestIdEsc' AND branchid='$branchIdEsc'
            LIMIT 1");
        ova_flash_set($updated ? ova_alert("success", "Contest status updated successfully.") : ova_alert("error", "Contest status could not be updated."));
    }
    header("location:".ova_admin_url(array("contest" => $contestId), "#contest-list"));
    exit();
}

if(isset($_POST["save_voting_candidate"])){
    foreach($candidateForm as $key => $value){
        $candidateForm[$key] = trim((string)(isset($_POST[$key]) ? $_POST[$key] : ""));
    }
    $candidateErrors = array();
    $candidateContest = voting_fetch_contest_by_id($con, $candidateForm["contestid"], $branchId);
    if(!$candidateContest){
        $candidateErrors[] = "Select a valid contest before adding a candidate.";
    }
    if($candidateForm["candidatename"] === ""){
        $candidateErrors[] = "Candidate name is required.";
    }
    if(!in_array($candidateForm["status"], array("active", "inactive"), true)){
        $candidateForm["status"] = "active";
    }
    $displayOrder = (int)$candidateForm["displayorder"];
    $photoPath = $candidateForm["photofile"];
    $videoPath = $candidateForm["videofile"];
    $imageError = "";
    $videoError = "";
    if(isset($_FILES["candidate_photo"]) && isset($_FILES["candidate_photo"]["error"]) && (int)$_FILES["candidate_photo"]["error"] !== UPLOAD_ERR_NO_FILE){
        $storedPhoto = voting_store_media_file($_FILES["candidate_photo"], "image", $imageError);
        if($storedPhoto === false){
            $candidateErrors[] = $imageError;
        }elseif($storedPhoto !== ""){
            $photoPath = $storedPhoto;
        }
    }
    if(isset($_FILES["candidate_video"]) && isset($_FILES["candidate_video"]["error"]) && (int)$_FILES["candidate_video"]["error"] !== UPLOAD_ERR_NO_FILE){
        $storedVideo = voting_store_media_file($_FILES["candidate_video"], "video", $videoError);
        if($storedVideo === false){
            $candidateErrors[] = $videoError;
        }elseif($storedVideo !== ""){
            $videoPath = $storedVideo;
        }
    }

    if(empty($candidateErrors)){
        $candidateId = $candidateForm["candidateid"] !== "" ? $candidateForm["candidateid"] : voting_generate_id("VOTE_CAND_");
        $existingCandidate = voting_fetch_candidate_by_id($con, $candidateId, "", true);
        $candidateIdEsc = mysqli_real_escape_string($con, $candidateId);
        $contestIdEsc = mysqli_real_escape_string($con, $candidateForm["contestid"]);
        $nameEsc = mysqli_real_escape_string($con, $candidateForm["candidatename"]);
        $numberEsc = mysqli_real_escape_string($con, $candidateForm["contestantnumber"]);
        $classEsc = mysqli_real_escape_string($con, $candidateForm["classlabel"]);
        $houseEsc = mysqli_real_escape_string($con, $candidateForm["houselabel"]);
        $genderEsc = mysqli_real_escape_string($con, $candidateForm["gender"]);
        $photoEsc = mysqli_real_escape_string($con, $photoPath);
        $videoEsc = mysqli_real_escape_string($con, $videoPath);
        $videoLinkEsc = mysqli_real_escape_string($con, $candidateForm["videolink"]);
        $sloganEsc = mysqli_real_escape_string($con, $candidateForm["slogan"]);
        $profileEsc = mysqli_real_escape_string($con, $candidateForm["profiletext"]);
        $statusEsc = mysqli_real_escape_string($con, $candidateForm["status"]);
        $recordedByEsc = mysqli_real_escape_string($con, voting_current_user_id());

        $exists = is_array($existingCandidate);
        if($exists){
            $updated = @mysqli_query($con, "UPDATE tblvotingcandidate SET
                contestid='$contestIdEsc',
                candidatename='$nameEsc',
                contestantnumber='$numberEsc',
                classlabel='$classEsc',
                houselabel='$houseEsc',
                gender='$genderEsc',
                photofile='$photoEsc',
                videofile='$videoEsc',
                videolink='$videoLinkEsc',
                slogan='$sloganEsc',
                profiletext='$profileEsc',
                displayorder='$displayOrder',
                status='$statusEsc',
                updatedat=NOW()
                WHERE candidateid='$candidateIdEsc'
                LIMIT 1");
            if($updated){
                $historyDetails = array();
                $changedFields = ova_candidate_changed_fields($existingCandidate, array(
                    "contestid" => $candidateForm["contestid"],
                    "candidatename" => $candidateForm["candidatename"],
                    "contestantnumber" => $candidateForm["contestantnumber"],
                    "classlabel" => $candidateForm["classlabel"],
                    "houselabel" => $candidateForm["houselabel"],
                    "gender" => $candidateForm["gender"],
                    "videolink" => $candidateForm["videolink"],
                    "slogan" => $candidateForm["slogan"],
                    "profiletext" => $candidateForm["profiletext"],
                    "displayorder" => (string)$displayOrder,
                    "status" => $candidateForm["status"]
                ));
                if(!empty($changedFields)){
                    $historyDetails[] = "Updated fields: ".implode(", ", $changedFields).".";
                }
                if(trim((string)$existingCandidate["photofile"]) !== trim((string)$photoPath)){
                    $historyDetails[] = trim((string)$photoPath) !== "" ? "Campaign photo updated." : "Campaign photo changed.";
                    if(trim((string)$existingCandidate["photofile"]) !== "" && trim((string)$existingCandidate["photofile"]) !== trim((string)$photoPath)){
                        voting_delete_media_file((string)$existingCandidate["photofile"]);
                    }
                }
                if(trim((string)$existingCandidate["videofile"]) !== trim((string)$videoPath)){
                    $historyDetails[] = trim((string)$videoPath) !== "" ? "Campaign video upload updated." : "Campaign video upload changed.";
                    if(trim((string)$existingCandidate["videofile"]) !== "" && trim((string)$existingCandidate["videofile"]) !== trim((string)$videoPath)){
                        voting_delete_media_file((string)$existingCandidate["videofile"]);
                    }
                }
                if(empty($historyDetails)){
                    $historyDetails[] = "Candidate profile saved with no major field change detected.";
                }
                voting_campaign_history_log(
                    $con,
                    $candidateForm["contestid"],
                    $candidateId,
                    $candidateForm["candidatename"],
                    "candidate_updated",
                    "Candidate profile updated",
                    implode(" ", $historyDetails),
                    voting_current_user_id()
                );
                ova_flash_set(ova_alert("success", "Candidate updated successfully."));
                header("location:".ova_admin_url(array("contest" => $candidateForm["contestid"]), "#candidate-form"));
                exit();
            }
            $flashMessage = ova_alert("error", "The candidate could not be updated right now.");
        }else{
            $saved = @mysqli_query($con, "INSERT INTO tblvotingcandidate(
                candidateid, contestid, candidatename, contestantnumber, classlabel, houselabel, gender,
                photofile, videofile, videolink, slogan, profiletext, displayorder, status, recordedby, datetimeentry, updatedat
            ) VALUES (
                '$candidateIdEsc', '$contestIdEsc', '$nameEsc', '$numberEsc', '$classEsc', '$houseEsc', '$genderEsc',
                '$photoEsc', '$videoEsc', '$videoLinkEsc', '$sloganEsc', '$profileEsc', '$displayOrder', '$statusEsc', '$recordedByEsc', NOW(), NOW()
            )");
            if($saved){
                $historyDetails = array();
                if($photoPath !== ""){
                    $historyDetails[] = "Photo uploaded.";
                }
                if($videoPath !== ""){
                    $historyDetails[] = "Video uploaded.";
                }
                if(trim((string)$candidateForm["videolink"]) !== ""){
                    $historyDetails[] = "Video link added.";
                }
                voting_campaign_history_log(
                    $con,
                    $candidateForm["contestid"],
                    $candidateId,
                    $candidateForm["candidatename"],
                    "candidate_created",
                    "Candidate profile created",
                    implode(" ", $historyDetails),
                    voting_current_user_id()
                );
                ova_flash_set(ova_alert("success", "Candidate added successfully."));
                header("location:".ova_admin_url(array("contest" => $candidateForm["contestid"]), "#candidate-form"));
                exit();
            }
            $flashMessage = ova_alert("error", "The candidate could not be saved right now.");
        }
    }else{
        $flashMessage = ova_alert("warning", implode(" ", $candidateErrors));
    }
}

if(isset($_POST["remove_voting_candidate_media"])){
    $candidateId = trim((string)(isset($_POST["candidateid"]) ? $_POST["candidateid"] : ""));
    $contestId = trim((string)(isset($_POST["contestid"]) ? $_POST["contestid"] : ""));
    $mediaType = trim((string)(isset($_POST["mediatype"]) ? $_POST["mediatype"] : ""));
    $candidateRow = voting_fetch_candidate_by_id($con, $candidateId, "", true);
    if($candidateRow){
        $candidateContest = voting_fetch_contest_by_id($con, (string)$candidateRow["contestid"], $branchId);
        if($candidateContest){
            $fieldName = "";
            $historyTitle = "";
            $historyDetails = "";
            $oldPath = "";
            if($mediaType === "photo"){
                $fieldName = "photofile";
                $historyTitle = "Candidate photo removed";
                $historyDetails = "The campaign photo was removed from this profile.";
                $oldPath = trim((string)$candidateRow["photofile"]);
            }elseif($mediaType === "video"){
                $fieldName = "videofile";
                $historyTitle = "Candidate video removed";
                $historyDetails = "The uploaded campaign video was removed from this profile.";
                $oldPath = trim((string)$candidateRow["videofile"]);
            }elseif($mediaType === "videolink"){
                $fieldName = "videolink";
                $historyTitle = "Candidate video link removed";
                $historyDetails = "The external campaign video link was removed from this profile.";
                $oldPath = trim((string)$candidateRow["videolink"]);
            }

            if($fieldName !== ""){
                if($oldPath === ""){
                    ova_flash_set(ova_alert("warning", "There is no media in that slot to remove."));
                }else{
                    $candidateIdEsc = mysqli_real_escape_string($con, $candidateId);
                    $updated = @mysqli_query($con, "UPDATE tblvotingcandidate SET $fieldName='', updatedat=NOW() WHERE candidateid='$candidateIdEsc' LIMIT 1");
                    if($updated){
                        if($mediaType === "photo" || $mediaType === "video"){
                            voting_delete_media_file($oldPath);
                        }
                        voting_campaign_history_log(
                            $con,
                            (string)$candidateRow["contestid"],
                            $candidateId,
                            (string)$candidateRow["candidatename"],
                            "candidate_media_removed",
                            $historyTitle,
                            $historyDetails,
                            voting_current_user_id()
                        );
                        ova_flash_set(ova_alert("success", "Candidate media removed successfully."));
                    }else{
                        ova_flash_set(ova_alert("error", "That media could not be removed right now."));
                    }
                }
            }
            $contestId = (string)$candidateRow["contestid"];
        }
    }
    header("location:".ova_admin_url(array("contest" => $contestId, "edit_candidate" => $candidateId), "#candidate-form"));
    exit();
}

if(isset($_POST["set_voting_candidate_status"])){
    $candidateId = trim((string)(isset($_POST["candidateid"]) ? $_POST["candidateid"] : ""));
    $contestId = trim((string)(isset($_POST["contestid"]) ? $_POST["contestid"] : ""));
    $status = trim((string)(isset($_POST["status"]) ? $_POST["status"] : ""));
    if($candidateId !== "" && in_array($status, array("active", "inactive"), true)){
        $candidateRow = voting_fetch_candidate_by_id($con, $candidateId, "", true);
        $candidateIdEsc = mysqli_real_escape_string($con, $candidateId);
        $statusEsc = mysqli_real_escape_string($con, $status);
        @mysqli_query($con, "UPDATE tblvotingcandidate SET status='$statusEsc', updatedat=NOW() WHERE candidateid='$candidateIdEsc' LIMIT 1");
        if($candidateRow){
            voting_campaign_history_log(
                $con,
                (string)$candidateRow["contestid"],
                $candidateId,
                (string)$candidateRow["candidatename"],
                "candidate_status",
                "Candidate ".($status === "active" ? "activated" : "deactivated"),
                "Status changed to ".ucfirst($status).".",
                voting_current_user_id()
            );
        }
        ova_flash_set(ova_alert("success", "Candidate status updated successfully."));
    }
    header("location:".ova_admin_url(array("contest" => $contestId), "#candidate-list"));
    exit();
}

$contestList = voting_fetch_contests($con, $branchId);
$selectedContestId = trim((string)(isset($_GET["contest"]) ? $_GET["contest"] : ""));
if($selectedContestId === "" && !empty($contestList)){
    $selectedContestId = (string)$contestList[0]["contestid"];
}
$currentContest = $selectedContestId !== "" ? voting_fetch_contest_by_id($con, $selectedContestId, $branchId) : null;
$candidateList = $currentContest ? voting_fetch_candidates($con, $currentContest["contestid"], true) : array();
$leaderboardSummary = $currentContest ? voting_leaderboard_summary($currentContest, $candidateList) : array(
    "total_votes" => 0,
    "total_revenue" => 0,
    "leader_name" => "No contestants yet",
    "leader_votes" => 0,
    "candidate_count" => 0,
    "price_per_vote" => 0
);

$contestEditId = trim((string)(isset($_GET["edit_contest"]) ? $_GET["edit_contest"] : ""));
if($contestEditId !== ""){
    $contestEdit = voting_fetch_contest_by_id($con, $contestEditId, $branchId);
    if($contestEdit){
        $contestForm["contestid"] = (string)$contestEdit["contestid"];
        $contestForm["title"] = (string)$contestEdit["title"];
        $contestForm["tagline"] = (string)$contestEdit["tagline"];
        $contestForm["description"] = (string)$contestEdit["description"];
        $contestForm["votereligibility"] = (string)$contestEdit["votereligibility"];
        $contestForm["livemode"] = (string)$contestEdit["livemode"];
        $contestForm["pricepervote"] = number_format((float)$contestEdit["pricepervote"], 2, ".", "");
        $contestForm["minvotes"] = (string)$contestEdit["minvotes"];
        $contestForm["maxvotes"] = (string)$contestEdit["maxvotes"];
        $contestForm["startsat"] = ova_datetime_for_input($contestEdit["startsat"]);
        $contestForm["endsat"] = ova_datetime_for_input($contestEdit["endsat"]);
        $contestForm["status"] = (string)$contestEdit["status"];
    }
}

$candidateEditId = trim((string)(isset($_GET["edit_candidate"]) ? $_GET["edit_candidate"] : ""));
if($candidateEditId !== "" && !empty($candidateList)){
    foreach($candidateList as $candidateRow){
        if((string)$candidateRow["candidateid"] === $candidateEditId){
            $candidateForm["candidateid"] = (string)$candidateRow["candidateid"];
            $candidateForm["contestid"] = (string)$candidateRow["contestid"];
            $candidateForm["candidatename"] = (string)$candidateRow["candidatename"];
            $candidateForm["contestantnumber"] = (string)$candidateRow["contestantnumber"];
            $candidateForm["classlabel"] = (string)$candidateRow["classlabel"];
            $candidateForm["houselabel"] = (string)$candidateRow["houselabel"];
            $candidateForm["gender"] = (string)$candidateRow["gender"];
            $candidateForm["videolink"] = (string)$candidateRow["videolink"];
            $candidateForm["slogan"] = (string)$candidateRow["slogan"];
            $candidateForm["profiletext"] = (string)$candidateRow["profiletext"];
            $candidateForm["displayorder"] = (string)$candidateRow["displayorder"];
            $candidateForm["status"] = (string)$candidateRow["status"];
            $candidateForm["photofile"] = (string)$candidateRow["photofile"];
            $candidateForm["videofile"] = (string)$candidateRow["videofile"];
            break;
        }
    }
}

if($candidateForm["contestid"] === "" && $currentContest){
    $candidateForm["contestid"] = (string)$currentContest["contestid"];
}

$historyCandidateId = $candidateEditId !== "" ? $candidateEditId : "";
$historyFocusCandidate = $historyCandidateId !== "" ? voting_fetch_candidate_by_id($con, $historyCandidateId, "", true) : null;
$campaignHistory = $currentContest ? voting_fetch_campaign_history($con, $currentContest["contestid"], $historyCandidateId, 40) : array();

$contestStats = array(
    "live_count" => 0,
    "candidate_count" => 0,
    "total_votes" => 0,
    "total_revenue" => 0
);
foreach($contestList as $contestRow){
    if((isset($contestRow["resolved_status"]) ? $contestRow["resolved_status"] : voting_resolved_contest_state($contestRow)) === "live"){
        $contestStats["live_count"]++;
    }
    $contestStats["candidate_count"] += (int)$contestRow["candidatecount"];
    $contestStats["total_votes"] += (int)$contestRow["totalvotes"];
    $contestStats["total_revenue"] += (float)$contestRow["totalrevenue"];
}
?>
<!DOCTYPE html>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/online-voting.css">
</head>
<body class="vote-admin-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform vote-shell">
    <section class="vote-hero vote-hero--admin">
        <div class="vote-hero__copy">
            <span class="vote-hero__eyebrow">Contest Voting</span>
            <h1>Online Voting Manager</h1>
            <p>Open contests, publish candidates, set price per vote, and monitor live standings.</p>
        </div>
        <div class="vote-hero__stats">
            <article><span>Live Contests</span><strong><?php echo number_format((int)$contestStats["live_count"]); ?></strong></article>
            <article><span>Total Candidates</span><strong><?php echo number_format((int)$contestStats["candidate_count"]); ?></strong></article>
            <article><span>Total Votes</span><strong><?php echo number_format((int)$contestStats["total_votes"]); ?></strong></article>
            <article><span>Total Revenue</span><strong>GHS <?php echo number_format((float)$contestStats["total_revenue"], 2); ?></strong></article>
        </div>
    </section>

    <?php if($flashMessage !== ""){ echo $flashMessage; } ?>

    <div class="vote-admin-layout">
        <div class="vote-admin-column vote-admin-column--forms">
            <section class="vote-card" id="contest-form">
                <div class="vote-card__head">
                    <div>
                        <span class="vote-card__eyebrow">Contest Setup</span>
                        <h2><?php echo $contestForm["contestid"] !== "" ? "Edit Contest" : "Create Contest"; ?></h2>
                    </div>
                </div>
                <form method="post" action="<?php echo ova_esc(ova_admin_url(array("contest" => $selectedContestId, "edit_contest" => $contestForm["contestid"]), "#contest-form")); ?>" class="vote-form-grid">
                    <input type="hidden" name="contestid" value="<?php echo ova_esc($contestForm["contestid"]); ?>">
                    <label><span>Contest Title</span><input type="text" name="title" value="<?php echo ova_esc($contestForm["title"]); ?>" placeholder="Example: Miss Ayirebi SHS 2026" required></label>
                    <label><span>Tagline</span><input type="text" name="tagline" value="<?php echo ova_esc($contestForm["tagline"]); ?>" placeholder="Short campaign call to action"></label>
                    <label class="vote-form-grid__full"><span>Description</span><textarea name="description" rows="4" placeholder="Explain the contest, rules, and what voters should expect."><?php echo ova_esc($contestForm["description"]); ?></textarea></label>
                    <label><span>Who Can Vote</span>
                        <select name="votereligibility">
                            <option value="both"<?php echo $contestForm["votereligibility"] === "both" ? " selected" : ""; ?>>Students And Teachers</option>
                            <option value="students"<?php echo $contestForm["votereligibility"] === "students" ? " selected" : ""; ?>>Students Only</option>
                            <option value="teachers"<?php echo $contestForm["votereligibility"] === "teachers" ? " selected" : ""; ?>>Teachers Only</option>
                        </select>
                    </label>
                    <label><span>Live Result View</span>
                        <select name="livemode">
                            <option value="full"<?php echo $contestForm["livemode"] === "full" ? " selected" : ""; ?>>Full Leaderboard</option>
                            <option value="top3"<?php echo $contestForm["livemode"] === "top3" ? " selected" : ""; ?>>Top 3 Only</option>
                            <option value="totals"<?php echo $contestForm["livemode"] === "totals" ? " selected" : ""; ?>>Totals Only</option>
                            <option value="hidden"<?php echo $contestForm["livemode"] === "hidden" ? " selected" : ""; ?>>Hide Results Until Close</option>
                        </select>
                    </label>
                    <label><span>Price Per Vote (GHS)</span><input type="number" name="pricepervote" min="0.01" step="0.01" value="<?php echo ova_esc($contestForm["pricepervote"]); ?>" required></label>
                    <label><span>Minimum Votes Per Purchase</span><input type="number" name="minvotes" min="1" step="1" value="<?php echo ova_esc($contestForm["minvotes"]); ?>" required></label>
                    <label><span>Maximum Votes Per Purchase</span><input type="number" name="maxvotes" min="1" step="1" value="<?php echo ova_esc($contestForm["maxvotes"]); ?>" required></label>
                    <label><span>Open Time</span><input type="datetime-local" name="startsat" value="<?php echo ova_esc($contestForm["startsat"]); ?>"></label>
                    <label><span>Close Time</span><input type="datetime-local" name="endsat" value="<?php echo ova_esc($contestForm["endsat"]); ?>"></label>
                    <label><span>Status</span>
                        <select name="status">
                            <option value="draft"<?php echo $contestForm["status"] === "draft" ? " selected" : ""; ?>>Draft</option>
                            <option value="open"<?php echo $contestForm["status"] === "open" ? " selected" : ""; ?>>Open / Scheduled</option>
                            <option value="closed"<?php echo $contestForm["status"] === "closed" ? " selected" : ""; ?>>Closed</option>
                            <option value="archived"<?php echo $contestForm["status"] === "archived" ? " selected" : ""; ?>>Archived</option>
                        </select>
                    </label>
                    <div class="vote-form-grid__full vote-form-actions">
                        <button type="submit" name="save_voting_contest" class="vote-btn vote-btn--primary"><i class="fa fa-save"></i> Save Contest</button>
                        <?php if($contestForm["contestid"] !== ""){ ?>
                        <a href="<?php echo ova_esc(ova_admin_url(array("contest" => $selectedContestId), "#contest-form")); ?>" class="vote-btn vote-btn--ghost">Cancel Edit</a>
                        <?php } ?>
                    </div>
                </form>
            </section>

            <section class="vote-card" id="candidate-form">
                <div class="vote-card__head">
                    <div>
                        <span class="vote-card__eyebrow">Candidates</span>
                        <h2><?php echo $candidateForm["candidateid"] !== "" ? "Edit Candidate" : "Add Candidate"; ?></h2>
                    </div>
                </div>
                <form method="post" action="<?php echo ova_esc(ova_admin_url(array("contest" => $selectedContestId, "edit_candidate" => $candidateForm["candidateid"]), "#candidate-form")); ?>" enctype="multipart/form-data" class="vote-form-grid">
                    <input type="hidden" name="candidateid" value="<?php echo ova_esc($candidateForm["candidateid"]); ?>">
                    <input type="hidden" name="photofile" value="<?php echo ova_esc($candidateForm["photofile"]); ?>">
                    <input type="hidden" name="videofile" value="<?php echo ova_esc($candidateForm["videofile"]); ?>">
                    <label><span>Contest</span>
                        <select name="contestid" required>
                            <option value="">Select Contest</option>
                            <?php foreach($contestList as $contestRow){ ?>
                            <option value="<?php echo ova_esc($contestRow["contestid"]); ?>"<?php echo $candidateForm["contestid"] === (string)$contestRow["contestid"] ? " selected" : ""; ?>><?php echo ova_esc($contestRow["title"]); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <label><span>Contestant Number</span><input type="text" name="contestantnumber" value="<?php echo ova_esc($candidateForm["contestantnumber"]); ?>" placeholder="Example: 07"></label>
                    <label class="vote-form-grid__full"><span>Candidate Name</span><input type="text" name="candidatename" value="<?php echo ova_esc($candidateForm["candidatename"]); ?>" placeholder="Candidate display name" required></label>
                    <label><span>Class</span><input type="text" name="classlabel" value="<?php echo ova_esc($candidateForm["classlabel"]); ?>" placeholder="Example: Form 2 Science"></label>
                    <label><span>House</span><input type="text" name="houselabel" value="<?php echo ova_esc($candidateForm["houselabel"]); ?>" placeholder="Example: Blue House"></label>
                    <label><span>Gender</span><input type="text" name="gender" value="<?php echo ova_esc($candidateForm["gender"]); ?>" placeholder="Optional"></label>
                    <label><span>Display Order</span><input type="number" name="displayorder" min="0" step="1" value="<?php echo ova_esc($candidateForm["displayorder"]); ?>"></label>
                    <label class="vote-form-grid__full"><span>Slogan</span><input type="text" name="slogan" value="<?php echo ova_esc($candidateForm["slogan"]); ?>" placeholder="Short campaign slogan"></label>
                    <label class="vote-form-grid__full"><span>Campaign Message</span><textarea name="profiletext" rows="5" placeholder="Write a message that attracts voter attention."><?php echo ova_esc($candidateForm["profiletext"]); ?></textarea></label>
                    <label><span>Candidate Photo</span><input type="file" name="candidate_photo" accept=".jpg,.jpeg,.png,.gif,.webp"></label>
                    <label><span>Campaign Video Upload</span><input type="file" name="candidate_video" accept=".mp4,.webm,.ogg"></label>
                    <label class="vote-form-grid__full"><span>Or Video Link</span><input type="url" name="videolink" value="<?php echo ova_esc($candidateForm["videolink"]); ?>" placeholder="Optional YouTube or hosted video link"></label>
                    <?php if($candidateForm["candidateid"] !== "" && ($candidateForm["photofile"] !== "" || $candidateForm["videofile"] !== "" || $candidateForm["videolink"] !== "")){ ?>
                    <div class="vote-form-grid__full">
                        <div class="vote-media-manager">
                            <?php if($candidateForm["photofile"] !== ""){ ?>
                            <article class="vote-media-card">
                                <div class="vote-media-card__preview">
                                    <img src="<?php echo ova_esc($candidateForm["photofile"]); ?>" alt="<?php echo ova_esc($candidateForm["candidatename"]); ?>">
                                </div>
                                <div class="vote-media-card__body">
                                    <span class="vote-chip">Current Photo</span>
                                    <p><?php echo ova_esc(basename((string)$candidateForm["photofile"])); ?></p>
                                    <form method="post" action="<?php echo ova_esc(ova_admin_url(array("contest" => $candidateForm["contestid"], "edit_candidate" => $candidateForm["candidateid"]), "#candidate-form")); ?>">
                                        <input type="hidden" name="candidateid" value="<?php echo ova_esc($candidateForm["candidateid"]); ?>">
                                        <input type="hidden" name="contestid" value="<?php echo ova_esc($candidateForm["contestid"]); ?>">
                                        <input type="hidden" name="mediatype" value="photo">
                                        <button type="submit" name="remove_voting_candidate_media" class="vote-btn vote-btn--ghost"><i class="fa fa-trash-o"></i> Remove Photo</button>
                                    </form>
                                </div>
                            </article>
                            <?php } ?>
                            <?php if($candidateForm["videofile"] !== ""){ ?>
                            <article class="vote-media-card">
                                <div class="vote-media-card__preview vote-media-card__preview--video">
                                    <i class="fa fa-play-circle"></i>
                                </div>
                                <div class="vote-media-card__body">
                                    <span class="vote-chip">Uploaded Video</span>
                                    <p><?php echo ova_esc(basename((string)$candidateForm["videofile"])); ?></p>
                                    <form method="post" action="<?php echo ova_esc(ova_admin_url(array("contest" => $candidateForm["contestid"], "edit_candidate" => $candidateForm["candidateid"]), "#candidate-form")); ?>">
                                        <input type="hidden" name="candidateid" value="<?php echo ova_esc($candidateForm["candidateid"]); ?>">
                                        <input type="hidden" name="contestid" value="<?php echo ova_esc($candidateForm["contestid"]); ?>">
                                        <input type="hidden" name="mediatype" value="video">
                                        <button type="submit" name="remove_voting_candidate_media" class="vote-btn vote-btn--ghost"><i class="fa fa-trash-o"></i> Remove Video</button>
                                    </form>
                                </div>
                            </article>
                            <?php } ?>
                            <?php if($candidateForm["videolink"] !== ""){ ?>
                            <article class="vote-media-card">
                                <div class="vote-media-card__preview vote-media-card__preview--link">
                                    <i class="fa fa-link"></i>
                                </div>
                                <div class="vote-media-card__body">
                                    <span class="vote-chip">Video Link</span>
                                    <p><a href="<?php echo ova_esc($candidateForm["videolink"]); ?>" target="_blank" rel="noopener">Open current campaign link</a></p>
                                    <form method="post" action="<?php echo ova_esc(ova_admin_url(array("contest" => $candidateForm["contestid"], "edit_candidate" => $candidateForm["candidateid"]), "#candidate-form")); ?>">
                                        <input type="hidden" name="candidateid" value="<?php echo ova_esc($candidateForm["candidateid"]); ?>">
                                        <input type="hidden" name="contestid" value="<?php echo ova_esc($candidateForm["contestid"]); ?>">
                                        <input type="hidden" name="mediatype" value="videolink">
                                        <button type="submit" name="remove_voting_candidate_media" class="vote-btn vote-btn--ghost"><i class="fa fa-trash-o"></i> Remove Link</button>
                                    </form>
                                </div>
                            </article>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>
                    <label><span>Status</span>
                        <select name="status">
                            <option value="active"<?php echo $candidateForm["status"] === "active" ? " selected" : ""; ?>>Active</option>
                            <option value="inactive"<?php echo $candidateForm["status"] === "inactive" ? " selected" : ""; ?>>Inactive</option>
                        </select>
                    </label>
                    <div class="vote-form-grid__full vote-form-actions">
                        <button type="submit" name="save_voting_candidate" class="vote-btn vote-btn--primary"><i class="fa fa-image"></i> Save Candidate</button>
                        <?php if($candidateForm["candidateid"] !== ""){ ?>
                        <a href="<?php echo ova_esc(ova_admin_url(array("contest" => $selectedContestId), "#candidate-form")); ?>" class="vote-btn vote-btn--ghost">Cancel Edit</a>
                        <?php } ?>
                    </div>
                </form>
            </section>
        </div>
        <div class="vote-admin-column vote-admin-column--main">
            <section class="vote-card" id="contest-list">
                <div class="vote-card__head">
                    <div>
                        <span class="vote-card__eyebrow">Contest List</span>
                        <h2>Manage Contests</h2>
                    </div>
                </div>
                <?php if(!empty($contestList)){ ?>
                <div class="vote-admin-contests">
                    <?php foreach($contestList as $contestRow){ ?>
                    <article class="vote-contest-card<?php echo $selectedContestId === (string)$contestRow["contestid"] ? " vote-contest-card--selected" : ""; ?>">
                        <div class="vote-contest-card__top">
                            <div>
                                <h3><?php echo ova_esc($contestRow["title"]); ?></h3>
                                <p><?php echo ova_esc(trim((string)$contestRow["tagline"]) !== "" ? $contestRow["tagline"] : "No tagline added yet."); ?></p>
                            </div>
                            <span class="<?php echo voting_status_badge_class(isset($contestRow["resolved_status"]) ? $contestRow["resolved_status"] : voting_resolved_contest_state($contestRow)); ?>"><?php echo ova_esc(voting_status_label(isset($contestRow["resolved_status"]) ? $contestRow["resolved_status"] : voting_resolved_contest_state($contestRow))); ?></span>
                        </div>
                        <div class="vote-meta-row">
                            <span><strong>Votes:</strong> <?php echo number_format((int)$contestRow["totalvotes"]); ?></span>
                            <span><strong>Revenue:</strong> GHS <?php echo number_format((float)$contestRow["totalrevenue"], 2); ?></span>
                            <span><strong>Candidates:</strong> <?php echo number_format((int)$contestRow["candidatecount"]); ?></span>
                        </div>
                        <div class="vote-meta-row">
                            <span><strong>Window:</strong> <?php echo ova_esc(voting_contest_time_label($contestRow)); ?></span>
                            <span><strong>Price:</strong> GHS <?php echo number_format((float)$contestRow["pricepervote"], 2); ?>/vote</span>
                        </div>
                        <div class="vote-card-actions">
                            <a href="<?php echo ova_esc(ova_admin_url(array("contest" => $contestRow["contestid"]), "#contest-list")); ?>" class="vote-btn vote-btn--ghost">Manage</a>
                            <a href="<?php echo ova_esc(ova_admin_url(array("contest" => $contestRow["contestid"], "edit_contest" => $contestRow["contestid"]), "#contest-form")); ?>" class="vote-btn vote-btn--ghost">Edit</a>
                            <a href="online-voting.php?contest=<?php echo rawurlencode((string)$contestRow["contestid"]); ?>" class="vote-btn vote-btn--ghost" target="_blank">Preview</a>
                        </div>
                        <div class="vote-status-actions">
                            <?php foreach(array("draft" => "Set Draft", "open" => "Open", "closed" => "Close", "archived" => "Archive") as $statusKey => $statusLabel){ ?>
                            <form method="post" action="<?php echo ova_esc(ova_admin_url(array("contest" => $contestRow["contestid"]), "#contest-list")); ?>">
                                <input type="hidden" name="contestid" value="<?php echo ova_esc($contestRow["contestid"]); ?>">
                                <input type="hidden" name="status" value="<?php echo ova_esc($statusKey); ?>">
                                <button type="submit" name="set_voting_contest_status" class="vote-btn vote-btn--micro"><?php echo ova_esc($statusLabel); ?></button>
                            </form>
                            <?php } ?>
                        </div>
                    </article>
                    <?php } ?>
                </div>
                <?php }else{ ?>
                <div class="vote-empty-state">
                    <h3>No contests yet</h3>
                    <p>Create the first pageantry contest and candidates will start appearing here.</p>
                </div>
                <?php } ?>
            </section>

            <section class="vote-card" id="candidate-list">
                <div class="vote-card__head">
                    <div>
                        <span class="vote-card__eyebrow">Live Contest Board</span>
                        <h2><?php echo $currentContest ? ova_esc($currentContest["title"]) : "Select a contest"; ?></h2>
                    </div>
                    <?php if($currentContest){ ?>
                    <a href="online-voting.php?contest=<?php echo rawurlencode((string)$currentContest["contestid"]); ?>" class="vote-btn vote-btn--ghost" target="_blank">Open Voter View</a>
                    <?php } ?>
                </div>
                <?php if($currentContest){ ?>
                <div class="vote-summary-strip">
                    <article><span>Total Votes</span><strong><?php echo number_format((int)$leaderboardSummary["total_votes"]); ?></strong></article>
                    <article><span>Total Revenue</span><strong>GHS <?php echo number_format((float)$leaderboardSummary["total_revenue"], 2); ?></strong></article>
                    <article><span>Leading Candidate</span><strong><?php echo ova_esc($leaderboardSummary["leader_name"]); ?></strong></article>
                    <article><span>Price Per Vote</span><strong>GHS <?php echo number_format((float)$leaderboardSummary["price_per_vote"], 2); ?></strong></article>
                </div>
                <div class="vote-candidate-grid vote-candidate-grid--admin">
                    <?php foreach($candidateList as $candidateRow){ ?>
                    <article class="vote-candidate-card">
                        <div class="vote-candidate-card__media">
                            <img src="<?php echo ova_esc(voting_candidate_photo($candidateRow)); ?>" alt="<?php echo ova_esc($candidateRow["candidatename"]); ?>">
                        </div>
                        <div class="vote-candidate-card__body">
                            <div class="vote-candidate-card__meta">
                                <span class="vote-chip">No. <?php echo ova_esc(trim((string)$candidateRow["contestantnumber"]) !== "" ? $candidateRow["contestantnumber"] : "--"); ?></span>
                                <span class="<?php echo voting_status_badge_class(trim((string)$candidateRow["status"]) === "active" ? "live" : "closed"); ?>"><?php echo ova_esc(ucfirst((string)$candidateRow["status"])); ?></span>
                            </div>
                            <h3><?php echo ova_esc($candidateRow["candidatename"]); ?></h3>
                            <p class="vote-candidate-card__sub"><?php echo ova_esc(trim((string)$candidateRow["classlabel"]." ".$candidateRow["houselabel"])); ?></p>
                            <p class="vote-candidate-card__slogan"><?php echo ova_esc(trim((string)$candidateRow["slogan"]) !== "" ? $candidateRow["slogan"] : "No slogan added yet."); ?></p>
                            <div class="vote-progress">
                                <div class="vote-progress__label"><span>Votes</span><strong><?php echo number_format((int)$candidateRow["totalvotes"]); ?></strong></div>
                                <div class="vote-progress__bar"><span style="width:<?php echo $leaderboardSummary["total_votes"] > 0 ? min(100, round((((int)$candidateRow["totalvotes"]) / max(1, (int)$leaderboardSummary["total_votes"])) * 100, 2)) : 0; ?>%"></span></div>
                            </div>
                            <div class="vote-card-actions">
                                <a href="<?php echo ova_esc(ova_admin_url(array("contest" => $currentContest["contestid"], "edit_candidate" => $candidateRow["candidateid"]), "#candidate-form")); ?>" class="vote-btn vote-btn--ghost">Edit</a>
                                <form method="post" action="<?php echo ova_esc(ova_admin_url(array("contest" => $currentContest["contestid"]), "#candidate-list")); ?>">
                                    <input type="hidden" name="candidateid" value="<?php echo ova_esc($candidateRow["candidateid"]); ?>">
                                    <input type="hidden" name="contestid" value="<?php echo ova_esc($currentContest["contestid"]); ?>">
                                    <input type="hidden" name="status" value="<?php echo trim((string)$candidateRow["status"]) === "active" ? "inactive" : "active"; ?>">
                                    <button type="submit" name="set_voting_candidate_status" class="vote-btn vote-btn--ghost"><?php echo trim((string)$candidateRow["status"]) === "active" ? "Deactivate" : "Activate"; ?></button>
                                </form>
                            </div>
                        </div>
                    </article>
                    <?php } ?>
                </div>
                <?php }else{ ?>
                <div class="vote-empty-state">
                    <h3>No contest selected yet</h3>
                    <p>Create a contest or choose one from the list to start adding candidates and monitoring results.</p>
                </div>
                <?php } ?>
            </section>

            <section class="vote-card" id="campaign-history">
                <div class="vote-card__head">
                    <div>
                        <span class="vote-card__eyebrow">Campaign History</span>
                        <h2><?php echo $historyFocusCandidate ? ova_esc($historyFocusCandidate["candidatename"]) : "Contest Timeline"; ?></h2>
                        <p class="vote-card__lead">
                            <?php
                            if($historyFocusCandidate){
                                echo "Showing campaign updates for this candidate only.";
                            }elseif($currentContest){
                                echo "Showing the latest candidate updates across this contest.";
                            }else{
                                echo "Select a contest to start seeing campaign history.";
                            }
                            ?>
                        </p>
                    </div>
                    <?php if($historyFocusCandidate){ ?>
                    <a href="<?php echo ova_esc(ova_admin_url(array("contest" => $selectedContestId), "#campaign-history")); ?>" class="vote-btn vote-btn--ghost">Show All Contest History</a>
                    <?php } ?>
                </div>
                <?php if($currentContest){ ?>
                    <?php if(!empty($campaignHistory)){ ?>
                    <div class="vote-history-list">
                        <?php foreach($campaignHistory as $historyRow){ ?>
                        <article class="vote-history-item">
                            <div class="vote-history-item__head">
                                <div>
                                    <span class="vote-chip"><?php echo ova_esc(ucwords(str_replace("_", " ", (string)$historyRow["actiontype"]))); ?></span>
                                    <h3><?php echo ova_esc($historyRow["actiontitle"]); ?></h3>
                                </div>
                                <time><?php echo ova_esc(ova_history_datetime($historyRow["datetimeentry"])); ?></time>
                            </div>
                            <p class="vote-history-item__candidate"><?php echo ova_esc(trim((string)$historyRow["display_candidate_name"]) !== "" ? $historyRow["display_candidate_name"] : "Candidate"); ?></p>
                            <?php if(trim((string)$historyRow["actiondetails"]) !== ""){ ?>
                            <p class="vote-history-item__details"><?php echo ova_esc($historyRow["actiondetails"]); ?></p>
                            <?php } ?>
                            <p class="vote-history-item__meta">Recorded by <?php echo ova_esc(trim((string)$historyRow["actorname"]) !== "" ? $historyRow["actorname"] : "System"); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                    <?php }else{ ?>
                    <div class="vote-empty-state vote-empty-state--soft">
                        <h3>No campaign history yet</h3>
                        <p>Candidate updates, status changes, and media removals will start appearing here.</p>
                    </div>
                    <?php } ?>
                <?php }else{ ?>
                <div class="vote-empty-state vote-empty-state--soft">
                    <h3>No contest selected yet</h3>
                    <p>Choose a contest first and the campaign timeline will appear here.</p>
                </div>
                <?php } ?>
            </section>
        </div>
    </div>
</div>
</body>
</html>
