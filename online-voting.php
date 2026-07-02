<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("voting-utils.php");
ensure_voting_tables($con);

if(!(voting_current_user_can_vote() || voting_is_admin())){
    header("location:index.php");
    exit();
}

function ov_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function ov_flash_take(){
    if(!isset($_SESSION["ONLINE_VOTING_MESSAGE"])){
        return "";
    }
    $message = (string)$_SESSION["ONLINE_VOTING_MESSAGE"];
    unset($_SESSION["ONLINE_VOTING_MESSAGE"]);
    return $message;
}

function ov_money($amount){
    return "GHS ".number_format((float)$amount, 2);
}

function ov_candidate_by_id($candidates, $candidateId){
    foreach((array)$candidates as $candidate){
        if((string)$candidate["candidateid"] === (string)$candidateId){
            return $candidate;
        }
    }
    return null;
}

$branchId = voting_default_branch_id($con);
$viewerIsAdmin = voting_is_admin();
$viewerCanVote = voting_current_user_can_vote();
$systemType = $viewerIsAdmin ? "" : voting_current_system_type();
$flashMessage = ov_flash_take();
$voter = $viewerCanVote ? voting_get_current_voter($con) : null;

$contestList = voting_fetch_contests($con, $branchId, $viewerCanVote ? $systemType : "");
$selectedContestId = trim((string)(isset($_GET["contest"]) ? $_GET["contest"] : ""));
if($selectedContestId === "" && !empty($contestList)){
    $selectedContestId = (string)$contestList[0]["contestid"];
}
$currentContest = $selectedContestId !== "" ? voting_fetch_contest_by_id($con, $selectedContestId, $branchId) : null;
if(!$currentContest && !empty($contestList)){
    $currentContest = $contestList[0];
    $selectedContestId = (string)$currentContest["contestid"];
}
$candidateList = $currentContest ? voting_fetch_candidates($con, $currentContest["contestid"]) : array();
$selectedCandidateId = trim((string)(isset($_GET["candidate"]) ? $_GET["candidate"] : ""));
$selectedCandidate = ov_candidate_by_id($candidateList, $selectedCandidateId);
if(!$selectedCandidate && !empty($candidateList)){
    $selectedCandidate = $candidateList[0];
}
$summary = $currentContest ? voting_leaderboard_summary($currentContest, $candidateList) : array(
    "total_votes" => 0,
    "total_revenue" => 0,
    "leader_name" => "No contestants yet",
    "leader_votes" => 0,
    "candidate_count" => 0,
    "price_per_vote" => 0
);
$resolvedStatus = $currentContest ? (isset($currentContest["resolved_status"]) ? $currentContest["resolved_status"] : voting_resolved_contest_state($currentContest)) : "";
$contestIsOpen = $currentContest ? voting_contest_is_open($currentContest) : false;
$contestAllowsRole = $currentContest ? voting_contest_accepts_role($currentContest, $systemType) : false;
list($minVotes, $maxVotes) = $currentContest ? voting_vote_quantity_bounds($currentContest) : array(1, 1);
$liveMode = $currentContest ? strtolower(trim((string)$currentContest["livemode"])) : "full";
$showFullBoard = $viewerIsAdmin || $liveMode === "full" || $liveMode === "top3";
$showTopThreeOnly = !$viewerIsAdmin && $liveMode === "top3";
$showTotalsOnly = !$viewerIsAdmin && $liveMode === "totals";
$hideResults = !$viewerIsAdmin && $liveMode === "hidden" && $resolvedStatus !== "closed";
?>
<!DOCTYPE html>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/online-voting.css">
<?php if($contestIsOpen && !$hideResults){ ?>
<meta http-equiv="refresh" content="60">
<?php } ?>
</head>
<body class="vote-public-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform vote-shell">
    <section class="vote-hero">
        <div class="vote-hero__copy">
            <span class="vote-hero__eyebrow">Live Contest Voting</span>
            <h1><?php echo $currentContest ? ov_esc($currentContest["title"]) : "Online Voting"; ?></h1>
            <p><?php echo $currentContest ? ov_esc(trim((string)$currentContest["tagline"]) !== "" ? $currentContest["tagline"] : "Support your favourite contestant and follow the live contest board from your phone.") : "No contest is available right now."; ?></p>
            <?php if($currentContest){ ?>
            <div class="vote-hero__meta">
                <span class="<?php echo voting_status_badge_class($resolvedStatus); ?>"><?php echo ov_esc(voting_status_label($resolvedStatus)); ?></span>
                <?php if(voting_contest_time_label($currentContest) !== ""){ ?><span class="vote-pill"><?php echo ov_esc(voting_contest_time_label($currentContest)); ?></span><?php } ?>
                <span class="vote-pill"><?php echo ov_esc(ucfirst((string)$currentContest["votereligibility"])); ?> voting</span>
            </div>
            <?php } ?>
        </div>
        <div class="vote-hero__stats">
            <article><span>Price Per Vote</span><strong><?php echo ov_money(isset($summary["price_per_vote"]) ? $summary["price_per_vote"] : 0); ?></strong></article>
            <article><span>Total Votes</span><strong><?php echo number_format((int)$summary["total_votes"]); ?></strong></article>
            <article><span>Contestants</span><strong><?php echo number_format((int)$summary["candidate_count"]); ?></strong></article>
            <article><span>Leading Now</span><strong><?php echo ov_esc($summary["leader_name"]); ?></strong></article>
        </div>
    </section>

    <?php if($flashMessage !== ""){ echo $flashMessage; } ?>

    <?php if($currentContest && $viewerIsAdmin){ ?>
    <div class="vote-alert vote-alert--info">Preview mode is active for admin. Open the voter dashboard to monitor the same live contest experience students and teachers will see.</div>
    <?php } ?>

    <?php if(!$currentContest){ ?>
    <section class="vote-card vote-empty-state">
        <h2>No contest is active yet</h2>
        <p>The school has not published a contest for voting right now. Check back later.</p>
    </section>
    <?php }else{ ?>
    <div class="vote-public-layout">
        <div class="vote-public-column vote-public-column--main">
            <?php if(!empty($contestList)){ ?>
            <section class="vote-card">
                <div class="vote-card__head">
                    <div>
                        <span class="vote-card__eyebrow">Contests</span>
                        <h2>Choose a contest</h2>
                    </div>
                </div>
                <div class="vote-tab-strip">
                    <?php foreach($contestList as $contestRow){ ?>
                    <a href="online-voting.php?contest=<?php echo rawurlencode((string)$contestRow["contestid"]); ?>" class="vote-tab<?php echo (string)$contestRow["contestid"] === (string)$selectedContestId ? " vote-tab--active" : ""; ?>">
                        <strong><?php echo ov_esc($contestRow["title"]); ?></strong>
                        <span><?php echo ov_esc(voting_status_label(isset($contestRow["resolved_status"]) ? $contestRow["resolved_status"] : voting_resolved_contest_state($contestRow))); ?></span>
                    </a>
                    <?php } ?>
                </div>
            </section>
            <?php } ?>

            <section class="vote-card">
                <div class="vote-card__head">
                    <div>
                        <span class="vote-card__eyebrow">Live Dashboard</span>
                        <h2><?php echo $hideResults ? "Voting is open" : "Current standings"; ?></h2>
                    </div>
                </div>
                <?php if($hideResults){ ?>
                <div class="vote-empty-state vote-empty-state--soft">
                    <h3>Live ranking is hidden for now</h3>
                    <p>Voting is still open, but the school has chosen to hide results until the contest closes. You can still open any contestant profile and vote.</p>
                </div>
                <?php }else{ ?>
                <div class="vote-leaderboard">
                    <?php
                    $leaderboardRows = $showTopThreeOnly ? array_slice($candidateList, 0, 3) : $candidateList;
                    foreach($leaderboardRows as $candidateRow){
                        $percent = $summary["total_votes"] > 0 ? min(100, round((((int)$candidateRow["totalvotes"]) / max(1, (int)$summary["total_votes"])) * 100, 2)) : 0;
                    ?>
                    <article class="vote-leaderboard__row">
                        <div class="vote-leaderboard__rank"><?php echo number_format((int)$candidateRow["rankposition"]); ?></div>
                        <div class="vote-leaderboard__body">
                            <div class="vote-leaderboard__label">
                                <strong><?php echo ov_esc($candidateRow["candidatename"]); ?></strong>
                                <?php if(!$showTotalsOnly){ ?><span><?php echo number_format((int)$candidateRow["totalvotes"]); ?> votes</span><?php } ?>
                            </div>
                            <div class="vote-progress__bar"><span style="width:<?php echo $percent; ?>%"></span></div>
                        </div>
                    </article>
                    <?php } ?>
                </div>
                <?php } ?>
            </section>
        </div>

        <aside class="vote-public-column vote-public-column--side">
            <?php if($selectedCandidate){ ?>
            <section class="vote-card vote-feature-card">
                <div class="vote-feature-card__media">
                    <img src="<?php echo ov_esc(voting_candidate_photo($selectedCandidate)); ?>" alt="<?php echo ov_esc($selectedCandidate["candidatename"]); ?>">
                </div>
                <div class="vote-feature-card__body">
                    <div class="vote-candidate-card__meta">
                        <span class="vote-chip">Contestant <?php echo ov_esc(trim((string)$selectedCandidate["contestantnumber"]) !== "" ? $selectedCandidate["contestantnumber"] : "--"); ?></span>
                        <?php if(trim((string)$selectedCandidate["classlabel"]) !== "" || trim((string)$selectedCandidate["houselabel"]) !== ""){ ?>
                        <span class="vote-chip vote-chip--soft"><?php echo ov_esc(trim((string)$selectedCandidate["classlabel"]." ".$selectedCandidate["houselabel"])); ?></span>
                        <?php } ?>
                    </div>
                    <h2><?php echo ov_esc($selectedCandidate["candidatename"]); ?></h2>
                    <p class="vote-feature-card__slogan"><?php echo ov_esc(trim((string)$selectedCandidate["slogan"]) !== "" ? $selectedCandidate["slogan"] : "Campaign message"); ?></p>
                    <div class="vote-feature-card__copy"><?php echo nl2br(ov_esc($selectedCandidate["profiletext"])); ?></div>
                    <?php if(trim((string)$selectedCandidate["videofile"]) !== ""){ ?>
                    <video controls preload="metadata" playsinline class="vote-feature-card__video">
                        <source src="<?php echo ov_esc($selectedCandidate["videofile"]); ?>">
                    </video>
                    <?php }elseif(trim((string)$selectedCandidate["videolink"]) !== ""){ ?>
                    <a class="vote-btn vote-btn--ghost vote-btn--wide" href="<?php echo ov_esc($selectedCandidate["videolink"]); ?>" target="_blank">Watch Campaign Video</a>
                    <?php } ?>
                    <?php if($viewerCanVote && $contestIsOpen && $contestAllowsRole){ ?>
                    <form method="post" action="online-voting-paystack-init.php" class="vote-buy-form" data-price="<?php echo number_format((float)$currentContest["pricepervote"], 2, ".", ""); ?>">
                        <input type="hidden" name="contestid" value="<?php echo ov_esc($currentContest["contestid"]); ?>">
                        <input type="hidden" name="candidateid" value="<?php echo ov_esc($selectedCandidate["candidateid"]); ?>">
                        <label><span>How many votes?</span><input type="number" name="votequantity" min="<?php echo (int)$minVotes; ?>" max="<?php echo (int)$maxVotes; ?>" value="<?php echo (int)$minVotes; ?>" required></label>
                        <div class="vote-buy-form__total">Total: <strong><?php echo ov_money((float)$currentContest["pricepervote"] * (int)$minVotes); ?></strong></div>
                        <button type="submit" class="vote-btn vote-btn--primary vote-btn--wide"><i class="fa fa-heart"></i> Vote With Paystack</button>
                    </form>
                    <?php }elseif(!$viewerCanVote){ ?>
                    <div class="vote-alert vote-alert--warning">Only students and teachers can vote in this contest.</div>
                    <?php }elseif(!$contestAllowsRole){ ?>
                    <div class="vote-alert vote-alert--warning">This contest is not open to your user group.</div>
                    <?php }else{ ?>
                    <div class="vote-alert vote-alert--warning">Voting is not open at the moment.</div>
                    <?php } ?>
                </div>
            </section>
            <?php } ?>
        </aside>
    </div>

    <section class="vote-card">
        <div class="vote-card__head">
            <div>
                <span class="vote-card__eyebrow">Contestants</span>
                <h2>Browse and support your favourite</h2>
            </div>
        </div>
        <div class="vote-candidate-grid">
            <?php foreach($candidateList as $candidateRow){ ?>
            <article class="vote-candidate-card">
                <div class="vote-candidate-card__media">
                    <img src="<?php echo ov_esc(voting_candidate_photo($candidateRow)); ?>" alt="<?php echo ov_esc($candidateRow["candidatename"]); ?>">
                </div>
                <div class="vote-candidate-card__body">
                    <div class="vote-candidate-card__meta">
                        <span class="vote-chip">No. <?php echo ov_esc(trim((string)$candidateRow["contestantnumber"]) !== "" ? $candidateRow["contestantnumber"] : "--"); ?></span>
                        <?php if(!$hideResults && !$showTotalsOnly){ ?><span class="vote-chip vote-chip--soft"><?php echo number_format((int)$candidateRow["totalvotes"]); ?> votes</span><?php } ?>
                    </div>
                    <h3><?php echo ov_esc($candidateRow["candidatename"]); ?></h3>
                    <p class="vote-candidate-card__sub"><?php echo ov_esc(trim((string)$candidateRow["classlabel"]." ".$candidateRow["houselabel"])); ?></p>
                    <p class="vote-candidate-card__slogan"><?php echo ov_esc(trim((string)$candidateRow["slogan"]) !== "" ? $candidateRow["slogan"] : "Tap to open the full profile."); ?></p>
                    <div class="vote-card-actions">
                        <a href="online-voting.php?contest=<?php echo rawurlencode((string)$currentContest["contestid"]); ?>&candidate=<?php echo rawurlencode((string)$candidateRow["candidateid"]); ?>" class="vote-btn vote-btn--ghost">View Profile</a>
                        <?php if($viewerCanVote && $contestIsOpen && $contestAllowsRole){ ?>
                        <form method="post" action="online-voting-paystack-init.php">
                            <input type="hidden" name="contestid" value="<?php echo ov_esc($currentContest["contestid"]); ?>">
                            <input type="hidden" name="candidateid" value="<?php echo ov_esc($candidateRow["candidateid"]); ?>">
                            <input type="hidden" name="votequantity" value="<?php echo (int)$minVotes; ?>">
                            <button type="submit" class="vote-btn vote-btn--primary">Vote Now</button>
                        </form>
                        <?php } ?>
                    </div>
                </div>
            </article>
            <?php } ?>
        </div>
    </section>
    <?php } ?>
</div>
<script>
(function(){
    var forms = document.querySelectorAll('.vote-buy-form');
    for(var i = 0; i < forms.length; i++){
        (function(form){
            var price = parseFloat(form.getAttribute('data-price') || '0');
            var input = form.querySelector('input[name="votequantity"]');
            var total = form.querySelector('.vote-buy-form__total strong');
            if(!input || !total){
                return;
            }
            var refreshTotal = function(){
                var qty = parseInt(input.value || '0', 10);
                if(isNaN(qty) || qty < 0){
                    qty = 0;
                }
                total.textContent = 'GHS ' + (price * qty).toFixed(2);
            };
            input.addEventListener('input', refreshTotal);
            refreshTotal();
        })(forms[i]);
    }
})();
</script>
</body>
</html>
