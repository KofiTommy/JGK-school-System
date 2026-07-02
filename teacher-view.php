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
<h2>Teacher Mode</h2>
<div id="admin" align="right" style="margin-top:-40px;">
<a href="uploads.php"><button class="btn-menu"><i class="fa fa-upload"></i> Upload</button></a>
<button class="btn-menu"><i class="fa fa-lock"></i> Question</button>
<button class="btn-menu"><i class="fa fa-book"></i> Tutorials</button>
<button class="btn-menu"><i class="fa fa-book"></i> Time Table</button>
<button class="btn-menu"><i class="fa fa-download"></i> Downloads</button>
<button class="btn-menu"><i class="fa fa-book"></i> Info</button>
<a href="bio-data.php"><button class="btn-menu"><i class="fa fa-book"></i> Bio-data</button></a>
<a href="logout.php"><button class="btn-menu"><i class="fa fa-power-off"></i> Logout </button></a>
</div>
	</div>

<div class="main-platform" align="center" style="">
	<br/><br/><br/><br/>
	<table border="0" width="100%">
		<tr>
			<td colspan="3">
				<?php 
				echo "<div><img src='images/comm.gif' width='100px' height='100px' alt='Person's Image' /></div><br/>";
				echo "<div style='padding:5px;background-color:whitesmoke;margin-top:-5px;margin-left:-5px;margin-right:-5px;margin-bottom:-5px;border-bottom:1px solid #ccc;color:royalblue'>Welcome! Moses Kofi Appiah</div>"; 


				?>
			</td>
		</tr>
		<tr>
			<td width="25%" valign="top">
				<h4>Notice Board
				<div align="right" style="margin-top:-16px;">
					<?php 
					echo "<a href='teacher-view.php'><i class='fa fa-refresh'> Refresh</i></a>";
				?>
				</div>
				</h4>
				
				<!--platform-->
			<div class="platform" align="left">
			<form method="post" action="student-view.php">

				<table border="1" width="100%">
					<tr>
						<td> 
							
						</td>
					</tr>

				</table>	
			</form>
			</div>
			
			</td>
			<td width="50%" valign="top">
				<h4 style="font-size:16px;color:royalblue;">Messages, Articles, Comments & Solutions</h4>
				
 
			</td>

			<td width="25%" valign="top">
				<h4>Menu</h4>
				<?php
				include("menu-side.php");
				?>
			</td>
		</tr>
	</table>
</div>
</body>
</html>