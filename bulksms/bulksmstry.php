<?php
if(isset($_POST["send"])){
$key="e7c782f1f1c83d0f373c"; //your unique API key;

@$message=$_POST["message"];
@$phone=$_POST["to"];
$message=urlencode($message);
$sender_id="AYISEC";

$url="http://clientlogin.bulksmsgh.com/smsapi?key=$key&to=$phone&msg=$message&sender_id=$sender_id";
                                                
$result=file_get_contents($url); //call url and store result;
                                           	                                                
	/****************API URL TO CHECK BALANCE****************/
	 //$url="http://clientlogin.bulksmsgh.com/api/smsapibalance?key=$key";                                                                                                                                          
	 switch($result){                                           
	 case "1000":
	 echo "Message sent";
	 break;
	 case "1002":
	 echo "Message not sent";
	 break;
	 case "1003":
	 echo "You don't have enough balance";
	 break;
	 case "1004":
	 echo "Invalid API Key";
	 break;
	 case "1005":
	 echo "Phone number not valid";
	 break;
	 case "1006":
	 echo "Invalid Sender ID";
	 break;
	 case "1008":
	 echo "Empty message";
	 break;
	 }
}
?>

<html>
<head>

</head>
<body>
	<form method="post" action="bulksmstry.php">
<label>To:</label>
<input type="text" id="to" name="to" placeholder="To:"/>
<br/>
<label>Message</label>
<textarea id="message" name="message"></textarea>
<br/>
<button id="send" name="send">SEND</button>
</form>
</body>
</html>
