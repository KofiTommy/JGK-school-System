<?php
$key="e7c782f1f1c83d0f373c"; //your unique API key;
$message=urlencode($message);
$sender_id="AYISEC";

$url="http://clientlogin.bulksmsgh.com/smsapi?key=$key&to=$phone&msg=$message&sender_id=$sender_id";
                                                
$result=file_get_contents($url); //call url and store result;
                                           	                                                
	/****************API URL TO CHECK BALANCE****************/
	 //$url="http://clientlogin.bulksmsgh.com/api/smsapibalance?key=$key";                                                                                                                                          
	 switch($result){                                           
	 case "1000":
	 //echo "Message sent";
	 $_SESSION['Message']="<div style='padding:8px;background-color:#efe;color:green;'>Message Successfully Sent</div>";
	 break;
	 case "1002":
	// echo "Message not sent";
	 $_SESSION['Message']="<div style='padding:8px;background-color:#fee;color:red;'>Message failed to Send</div>";
	
	 break;
	 case "1003":
	 //echo "You don't have enough balance";
	  $_SESSION['Message']="<div style='padding:8px;background-color:#fee;color:red;'>You don't have enough balance</div>";
	
	 break;
	 case "1004":
	 //echo "Invalid API Key";
	  $_SESSION['Message']="<div style='padding:8px;background-color:#fee;color:red;'>Invalid API Key</div>";
	
	 break;
	 case "1005":
	// echo "Phone number not valid";
	  $_SESSION['Message']="<div style='padding:8px;background-color:#fee;color:red;'>Invalid Phone Number</div>";
	
	 break;
	 case "1006":
	 //echo "Invalid Sender ID";
	  $_SESSION['Message']="<div style='padding:8px;background-color:#fee;color:red;'>Invalid Sender ID</div>";
	
	 break;
	 case "1008":
	// echo "Empty message";
	  $_SESSION['Message']="<div style='padding:8px;background-color:#fee;color:red;'>Empty Message</div>";
	
	 break;
	 }

?>
