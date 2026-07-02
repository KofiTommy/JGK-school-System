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
<a href="work.php"><button class="btn-menu"><i class="fa fa-book"></i> Work</button></a>
<a href="question.php"><button class="btn-menu"><i class="fa fa-lock"></i> Question</button></a>
<button class="btn-menu"><i class="fa fa-book"></i> Tutorials</button>
<button class="btn-menu"><i class="fa fa-book"></i> Time Table</button>
<button class="btn-menu"><i class="fa fa-download"></i> Downloads</button>
<button class="btn-menu"><i class="fa fa-money"></i> Fees</button>
<button class="btn-menu"><i class="fa fa-book"></i> Report</button>
<a href="student-view.php"><button class="btn-menu"><i class="fa fa-home"></i> Home</button></a>
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
			<form method="post" action="payments.php">

			<h3>Work
				<div align="right" style="margin-top:-30px;"><a href="student-view.php" style="color:red"><i class="fa fa-close"></a></i></div>
			</h3>
			
		
			<label>Work Id</label><br/>
			<input type="text" id="payment-id" name="payment-id" /><br/><br/>


			<label>Subject Id</label><br/>
			<select id="subject-id" name="student-id">
				<option value="Select Subject Id" selected>Select Subject Id </option>
			</select>
			<br/><br/>

			<label>Work</label><br/>
			<textarea id="question" name="question"></textarea><br/><br/>
				
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