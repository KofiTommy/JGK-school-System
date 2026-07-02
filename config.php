<?php
/////// Update your database login details here /////
$dbhost_name = "localhost"; // Your host name 
$database = "u131576917_jgk";       // Your database name
$username = "u131576917_jgk";            // Your login userid 
$password = "Akusika.Tom???@@1";            // Your password 
//////// End of database details of your server //////

//////// Do not Edit below /////////
try {
$dbo = new PDO('mysql:host='.$dbhost_name.';dbname='.$database, $username, $password);
} catch (PDOException $e) {
print "Error!: " . $e->getMessage() . "<br/>";
die();
}
?> 
