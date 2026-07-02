<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("voting-utils.php");
include_once("online-admission-utils.php");
ensure_voting_tables($con);

function voting_payment_flash($type, $message, $contestId = ""){
    $class = "vote-alert vote-alert--info";
    if($type === "success"){ $class = "vote-alert vote-alert--success"; }
    elseif($type === "error"){ $class = "vote-alert vote-alert--error"; }
    elseif($type === "warning"){ $class = "vote-alert vote-alert--warning"; }
    $_SESSION["ONLINE_VOTING_MESSAGE"] = "<div class=\"".$class."\">".htmlspecialchars((string)$message, ENT_QUOTES, "UTF-8")."</div>";
    $target = "online-voting.php".($contestId !== "" ? "?contest=".rawurlencode((string)$contestId) : "");
    header("location:".$target);
    exit();
}

if(!voting_current_user_can_vote()){
    voting_payment_flash("warning", "Only students and teachers can vote in the contest.");
}

$contestId = trim((string)(isset($_POST["contestid"]) ? $_POST["contestid"] : ""));
$candidateId = trim((string)(isset($_POST["candidateid"]) ? $_POST["candidateid"] : ""));
$branchId = voting_default_branch_id($con);
$contest = voting_fetch_contest_by_id($con, $contestId, $branchId);
if(!$contest){
    voting_payment_flash("error", "The selected contest could not be found.");
}
if(!voting_contest_is_open($contest)){
    voting_payment_flash("warning", "Voting is not open for this contest right now.", $contestId);
}
if(!voting_contest_accepts_role($contest, voting_current_system_type())){
    voting_payment_flash("warning", "This contest is not open to your user group.", $contestId);
}

$candidateList = voting_fetch_candidates($con, $contestId);
$candidate = null;
foreach($candidateList as $candidateRow){
    if((string)$candidateRow["candidateid"] === $candidateId){
        $candidate = $candidateRow;
        break;
    }
}
if(!$candidate){
    voting_payment_flash("warning", "Select a valid contestant before paying.", $contestId);
}

list($minVotes, $maxVotes) = voting_vote_quantity_bounds($contest);
$voteQuantity = (int)(isset($_POST["votequantity"]) ? $_POST["votequantity"] : $minVotes);
if($voteQuantity < $minVotes || $voteQuantity > $maxVotes){
    voting_payment_flash("warning", "Choose between ".$minVotes." and ".$maxVotes." votes per purchase.", $contestId);
}

$voter = voting_get_current_voter($con);
if(!$voter){
    voting_payment_flash("error", "Your voter profile could not be loaded right now.", $contestId);
}

$config = online_admission_paystack_config();
$config["callback_path"] = "online-voting-paystack-callback.php";
if(!online_admission_paystack_is_ready($config)){
    voting_payment_flash("error", "Paystack is not configured yet. Please contact the school.", $contestId);
}

$amount = ((float)$contest["pricepervote"]) * $voteQuantity;
if($amount <= 0){
    voting_payment_flash("warning", "The school has not set a valid price per vote yet.", $contestId);
}

$reference = voting_payment_reference();
$payload = array(
    "reference" => $reference,
    "email" => voting_payment_email($voter),
    "amount" => online_admission_money_minor_units($amount),
    "currency" => "GHS",
    "callback_url" => online_admission_payment_callback_url($config, "callback_path", "online-voting-paystack-callback.php"),
    "metadata" => array(
        "contestid" => (string)$contest["contestid"],
        "candidateid" => (string)$candidate["candidateid"],
        "candidate_name" => (string)$candidate["candidatename"],
        "vote_quantity" => (int)$voteQuantity,
        "voterid" => (string)$voter["userid"],
        "voter_type" => (string)$voter["systemtype"]
    )
);

$errorMessage = "";
$response = online_admission_paystack_initialize($config, $payload, $errorMessage);
if($response === false || !isset($response["data"]) || !is_array($response["data"]) || trim((string)(isset($response["data"]["authorization_url"]) ? $response["data"]["authorization_url"] : "")) === ""){
    voting_payment_flash("error", $errorMessage !== "" ? $errorMessage : "Paystack could not start the voting payment right now.", $contestId);
}

$saved = voting_create_payment_record($con, array(
    "contestid" => (string)$contest["contestid"],
    "candidateid" => (string)$candidate["candidateid"],
    "branchid" => $branchId,
    "voterid" => (string)$voter["userid"],
    "votersystemtype" => (string)$voter["systemtype"],
    "votername" => (string)$voter["displayname"],
    "voteremail" => voting_payment_email($voter),
    "votermobile" => isset($voter["mobile"]) ? (string)$voter["mobile"] : "",
    "votequantity" => $voteQuantity,
    "amount" => $amount,
    "currency" => "GHS",
    "gateway" => "paystack",
    "reference" => $reference,
    "accesscode" => isset($response["data"]["access_code"]) ? (string)$response["data"]["access_code"] : "",
    "authorizationurl" => isset($response["data"]["authorization_url"]) ? (string)$response["data"]["authorization_url"] : "",
    "gatewaytransactionid" => "",
    "status" => "initialized",
    "votescredited" => 0,
    "creditedat" => null,
    "verifiedat" => null,
    "gatewayresponse" => isset($response["message"]) ? (string)$response["message"] : "Initialized",
    "rawresponse" => isset($response["_raw"]) ? (string)$response["_raw"] : ""
));
if($saved === false){
    voting_payment_flash("error", "The voting payment session could not be recorded right now.", $contestId);
}

header("location:".(string)$response["data"]["authorization_url"]);
exit();
