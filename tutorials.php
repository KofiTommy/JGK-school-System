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
	<br/><br/><br/><br/>
<table>
<tr>
<td width="25%" valign="top"> 

		<!--Login-->
		<div class="data" align="left">
			<form method="post" action="#">

			<h3>Upload Subject
				<div align="right" style="margin-top:-30px;"><a href="teacher-view.php" style="color:red"><i class="fa fa-close"></a></i></div>
			</h3>
			<br/>
		
			<label>Subject</label><br/>
			<input type="text" id="subject" name="subject" /><br/><br/>


			<label>File</label><br/>
			<input type="file" id="subject-mark" name="subject-mark" />
			<br/>
			<br/>

			<label>Description</label>
			<textarea id="description" name="description" ></textarea>

			<br/><br/>
			<div align="center"><button class="btn"><i class="fa fa-send"></i> submit</button></div>

		</form>

		</div>

</td>

<td width="50%" valign="top"> 

</td>

<td width="25%" valign="top"> 

</td>
</tr>
</table>




</body>
</html>