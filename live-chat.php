<html>
<head>
<?php
include("links.php");
?>

</head>

<body style="background-image:url('images/Image_Collaborate_400x2471.png');">
	<!--Header-->
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
		include("header.php");
		?>
		
<div align="right" style="margin-top:-40px;">
<a href="contact-us.php"><button class="btn-menu"><i class="fa fa-phone"></i> Contact Us</button></a>
<button class="btn-menu"><i class="fa fa-book"></i> About Us</button>
<a href="index.php"><button class="btn-menu"><i class="fa fa-home"></i> Home</button></a>
</div>
	</div>
<br/><br/>
<div class="main" align="center" style="">
		<!--Login-->
		<div class="login" align="left">
			<form method="post" action="#">

			<h3>Live Chat   
					</h3>
			<br/>
		
			<label>Full Name</label><br/>
			<input type="text" id="fullname" name="fullname" /><br/><br/>


			<label>Email Address</label><br/>
			<input type="email" id="email" name="email" />
			<br/>
			<br/>

			<label>Reason</label>
			<textarea id="message" name="message" ></textarea>

			<br/><br/>
			<div align="center"><button class="btn"><i class="fa fa-comment"></i> Chat</button></div>

		</form>

		</div>

</div>
<!--Footer-->
<?php
include("footer.php");

?>
</body>
</html>