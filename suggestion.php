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
<button class="btn-menu"><i class="fa fa-comment"></i> Payment</button>
<a href="donation.php"><button class="btn-menu"><i class="fa fa-lock"></i> Donation</button></a>
<button class="btn-menu"><i class="fa fa-book"></i> Report</button>
<button class="btn-menu"><i class="fa fa-book"></i> Suggestion</button>
<button class="btn-menu"><i class="fa fa-money"></i> Fees</button>
<a href="parent-view.php"><button class="btn-menu"><i class="fa fa-home"></i> Home</button></a>
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
			<form method="post" action="suggestion.php">

			<h3>Suggestion
				<div align="right" style="margin-top:-30px;"><a href="parent-view.php" style="color:red"><i class="fa fa-close"></a></i></div>
			</h3>
			
		
			<label>Suggestion Id</label><br/>
			<input type="text" id="suggestion-id" name="suggestion-id" /><br/><br/>

			<label>Suggestion</label><br/>
			<textarea id="suggestion" name="suggestion"></textarea><br/><br/>
				
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