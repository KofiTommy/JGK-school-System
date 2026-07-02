<?php

    // Here we assume the user is using the combination of his clientId and clientSecret as credentials
 //   $auth = new BasicAuth("user233", "password23");
//include("hubtel/Api.php");
// Let us test the SDK
require './vendor/autoload.php';


$auth = new BasicAuth("tfpoatiu", "klgvoczq");

// instance of ApiHost
$apiHost = new ApiHost($auth);

// instance of AccountApi
$accountApi = new AccountApi($apiHost);

// set web console logging to false
$disableConsoleLogging = false;

// Let us try to send some message
$messagingApi = new MessagingApi($apiHost, $disableConsoleLogging);
try {
    // Send a quick message
    $messageResponse = $messagingApi->sendQuickMessage("S.H.S Abireye", "+233202311659", "Welcome to planet Hubtel!");

    if ($messageResponse instanceof MessageResponse) {
        echo $messageResponse->getStatus();
    } elseif ($messageResponse instanceof HttpResponse) {
        echo "\nServer Response Status : " . $messageResponse->getStatus();
    }
} catch (Exception $ex) {
    echo $ex->getTraceAsString();
}
