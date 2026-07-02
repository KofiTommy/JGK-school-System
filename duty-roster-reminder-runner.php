<?php
if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    echo "This runner is available from the command line only.";
    exit(1);
}

include("dbstring.php");
include("duty-roster-utils.php");
ensure_duty_roster_tables($con);

$referenceDate = isset($argv[1]) ? trim((string)$argv[1]) : null;
if($referenceDate !== null && $referenceDate !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)){
    echo "Usage: php duty-roster-reminder-runner.php [YYYY-MM-DD]\n";
    exit(1);
}

$summary = duty_roster_run_weekly_reminders($con, $referenceDate, "SYSTEM");

echo "Duty roster weekly reminder run completed.\n";
echo "Run week: ".duty_roster_format_date($summary['run_week_start'])." to ".duty_roster_format_date($summary['run_week_end'])."\n";
echo "Current duty week: ".duty_roster_format_date($summary['current_week_start'])." to ".duty_roster_format_date($summary['current_week_end'])."\n";
echo "Next duty week: ".duty_roster_format_date($summary['next_week_start'])." to ".duty_roster_format_date($summary['next_week_end'])."\n";
echo "Due reminders total: ".(int)$summary['total_due']."\n";
echo "This week due: ".(int)$summary['current_week_due']."\n";
echo "Next week due: ".(int)$summary['next_week_due']."\n";
echo "SMS sent: ".(int)$summary['sent']."\n";
echo "Skipped (already logged): ".(int)$summary['skipped']."\n";
echo "No phone number: ".(int)$summary['no_phone']."\n";
echo "Failed sends: ".(int)$summary['failed']."\n";

if(!empty($summary['items'])){
    echo "\nItems:\n";
    foreach($summary['items'] as $item){
        echo "- ".$item['teacher']." | ".$item['duty']." | ".$item['scope']." | ".$item['status']."\n";
    }
}
?>
