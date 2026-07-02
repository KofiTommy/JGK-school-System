<html>
<head>
<?php
include("links.php");
?>

</head>

<body>
	<!--Header-->
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
		<h2>M-OSMS</h2>
<div align="right" style="margin-top:-40px;">
<button class="btn-menu"><i class="fa fa-comment"></i> Live Chat</button>
<a href="uploads.php"><button class="btn-menu"><i class="fa fa-upload"></i> Upload</button></a>
<button class="btn-menu"><i class="fa fa-lock"></i> Question</button>
<button class="btn-menu"><i class="fa fa-book"></i> Tutorials</button>
<button class="btn-menu"><i class="fa fa-book"></i> Time Table</button>
<button class="btn-menu"><i class="fa fa-download"></i> Downloads</button>
<button class="btn-menu"><i class="fa fa-book"></i> Info</button>
<a href="bio-data.php"><button class="btn-menu"><i class="fa fa-book"></i> Bio-data</button></a>
<a href="teacher-view.php"><button class="btn-menu"><i class="fa fa-home"></i> Home</button></a>
<a href="logout.php"><button class="btn-menu"><i class="fa fa-power-off"></i> Logout </button></a>
</div>
	</div>
	<br/><br/>
<br/>
<br/>
<br/>


	<table>
		<tr>
			<td width="25%" valign="top">			


		<!--Login-->
		<div class="data" align="left">
			<form method="post" action="bio-data.php">

			<h3>Bio-data
				<div align="right" style="margin-top:-30px;"><a href="teacher-view.php" style="color:red"><i class="fa fa-close"></a></i></div>
			</h3>
			
		
			<label>First name</label><br/>
			<input type="text" id="firstname" name="firstname" /><br/><br/>

			<label>Surname</label><br/>
			<input type="text" id="surname" name="surname" /><br/><br/>

			<label>Othernames</label><br/>
			<input type="text" id="othernames" name="othernames" /><br/><br/>

			<label>Date Of Birth</label><br/>
			<input type="date" id="date-of-birth" name="date-of-birth" /><br/><br/>

			<label>Age</label><br/>
			<input type="text" id="age" name="age" /><br/><br/>

			<label>Email Address</label><br/>
			<input type="email" id="email" name="email" />
			<br/>
			<br/>

			<label>Address</label>
			<textarea id="address" name="address" ></textarea>
			<br/><br/>

			<label>Username</label><br/>
			<input type="text" id="username" name="username" /><br/><br/>

			<label>Password</label><br/>
			<input type="password" id="password" name="password" /><br/><br/>

			<div align="center"><button class="btn"><i class="fa fa-send"></i> Submit</button></div>

		</form>

		</div>
		<br/><br/><br/>


</td>
			<td width="50%" valign="top">

			</td>

			<td width="25%">

			</td>

		</tr>

	</table>


<!--Footer-->

</body>
</html>