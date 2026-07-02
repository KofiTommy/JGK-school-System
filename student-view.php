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
		<h2>Student Mode</h2>
<div id="admin" align="right" style="margin-top:-40px;">
<a href="work.php"><button class="btn-menu"><i class="fa fa-book"></i> Work</button></a>
<a href="question.php"><button class="btn-menu"><i class="fa fa-lock"></i> Question</button></a>
<button class="btn-menu"><i class="fa fa-book"></i> Tutorials</button>
<button class="btn-menu"><i class="fa fa-book"></i> Time Table</button>
<a href="downloads.php"><button class="btn-menu"><i class="fa fa-download"></i> Downloads</button></a>
<button class="btn-menu"><i class="fa fa-money"></i> Fees</button>
<button class="btn-menu"><i class="fa fa-book"></i> Report</button>
<a href="logout.php"><button class="btn-menu"><i class="fa fa-power-off"></i> Logout </button></a>
</div>
	</div>

<div class="main-platform" align="center" style="">
	<br/><br/><br/><br/>
	<table border="0" width="100%">
		<tr>
			<td colspan="3">
				<?php 
				echo "<div style='padding:5px;background-color:whitesmoke;margin-top:-5px;margin-left:-5px;margin-right:-5px;margin-bottom:-5px;border-bottom:1px solid #ccc;color:royalblue'>Welcome! Moses Kofi Appiah</div>"; 


				?>
			</td>
		</tr>
		<tr>
			<td width="25%" valign="top">
				<h4>Notice Board
				<div align="right" style="margin-top:-20px;">
					<?php 
					echo "<a href='student-view.php'><i class='fa fa-refresh'> Refresh</i></a>";
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
				<h4>Messages</h4>
				
 
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