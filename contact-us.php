<html>
<head>
<?php
include("links.php");
?>

</head>

<body style="background-image:url('images/Image_Collaborate_400x2472.png');">
	<!--Header-->
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
		<?php
		include("header.php");
		?>
<div align="right" style="margin-top:-40px;">
<a href="live-chat.php"><button class="btn-menu"><i class="fa fa-comment"></i> Live Chat</button></a>
<button class="btn-menu"><i class="fa fa-book"></i> About Us</button>
<a href="index.php"><button class="btn-menu"><i class="fa fa-home"></i> Home</button></a>
</div>
	</div>

<div class="main" align="center" style="">
		<!--Login-->
		<div class="login" align="left">
			<form id="formID" name="formID" method="post" action="contact-us.php">

			<h3>Contact Us   
			</h3>
			<br/>
		
			<label>Full Name</label><br/>
			<input type="text" id="fullname" name="fullname" class="validate[required,custom[onlyLetterSp]]"/><br/><br/>


			<label>Email Address</label><br/>
			<input type="email" id="email" name="email"  class="validate[required,custom[email]]"/>
			<br/>
			<br/>

			<label>Message</label>
			<textarea id="message" name="message" ></textarea>

			<br/><br/>
			<div align="center"><button class="btn"><i class="fa fa-send"></i> Submit</button></div>

		</form>

		</div>

</div>
<!--Footer-->
<?php
include("footer.php");

?>
</body>
</html>